<?php



// Catch all output and errors

ob_start();



// Enable error reporting for debugging

error_reporting(E_ALL);

ini_set('display_errors', 0);

ini_set('log_errors', 1);

ini_set('error_log', __DIR__ . '/php_errors.log');



// Set headers

header('Content-Type: application/json; charset=utf-8');

header('Access-Control-Allow-Origin: *');

header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

header('Access-Control-Allow-Headers: Content-Type');



// Handle preflight requests

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    http_response_code(200);

    exit;

}



/**

 * Automatically binds parameters to a prepared statement by detecting their types,

 * preventing manual type-string counting errors.

 */

function safeBindParam($stmt, ...$params) {

    $types = '';

    foreach ($params as $param) {

        if (is_int($param)) {

            $types .= 'i';

        } elseif (is_float($param)) {

            $types .= 'd';

        } else {

            $types .= 's';

        }

    }

    $stmt->bind_param($types, ...$params);

}



/**

 * Ensures churches.church_type exists before handlers query or write it.

 */

function ensureChurchTypeColumn(mysqli $conn): void

{

    $check = $conn->query("SHOW COLUMNS FROM churches LIKE 'church_type'");

    if ($check && $check->num_rows > 0) {

        return;

    }



    if (!$conn->query("ALTER TABLE churches ADD COLUMN church_type ENUM('kids','youth') NOT NULL DEFAULT 'kids'")) {

        throw new Exception('Failed to ensure church_type column: ' . $conn->error);

    }

}



// ── Safe deletion of uploaded files ─────────────────────────

/**

 * Safely deletes an uploaded image file from the server.

 * Handles both full URLs (https://sunday-school.online/uploads/...)

 * and relative paths (/uploads/...).

 * 

 * Security: validates path is within uploads dir, no traversal,

 * and the file is a real image. Never throws — returns bool.

 */

function deleteUploadedFile(?string $imageUrl): bool {

    if (empty($imageUrl)) return false;



    // Extract relative path from full URL or relative path

    $path = $imageUrl;

    if (strpos($path, 'https://') === 0 || strpos($path, 'http://') === 0) {

        $parsed = parse_url($path);

        $path = $parsed['path'] ?? '';

    }



    // Must start with /uploads/

    if (strpos($path, '/uploads/') !== 0) {

        error_log("deleteUploadedFile: path does not start with /uploads/: " . $imageUrl);

        return false;

    }



    // Build absolute file path

    $filePath = __DIR__ . $path;

    $realPath = @realpath($filePath);

    $uploadsDir = @realpath(__DIR__ . '/uploads');



    // Safety checks

    if (!$realPath || !$uploadsDir) {

        error_log("deleteUploadedFile: file not found or uploads dir missing: " . $imageUrl);

        return false;

    }



    // Path traversal protection

    if (strpos($realPath, $uploadsDir . DIRECTORY_SEPARATOR) !== 0 && $realPath !== $uploadsDir) {

        error_log("SECURITY deleteUploadedFile: path traversal attempt blocked: " . $imageUrl);

        return false;

    }



    // Must be a regular file

    if (!is_file($realPath)) {

        error_log("deleteUploadedFile: not a regular file: " . $imageUrl);

        return false;

    }



    // Delete file

    if (@unlink($realPath)) {

        error_log("✅ Cleaned up uploaded file: " . $path);

        return true;

    }



    error_log("❌ Failed to delete uploaded file: " . $path);

    return false;

}



// ── Game / QR processing for trips ─────────────────────────

function processGameQRCode()

{

    try {

        // Allow uncle or church admin

        checkUncleAuth();

        $churchId = getChurchId();

        $uncleId = $_SESSION['uncle_id'] ?? null;



        $tripId = intval($_REQUEST['trip'] ?? $_REQUEST['trip_id'] ?? 0);

        $studentId = intval($_REQUEST['id'] ?? $_REQUEST['student_id'] ?? 0);

        $game_action = strtolower(trim($_REQUEST['game_action'] ?? 'increment'));

        $amount = intval($_REQUEST['amount'] ?? 1);



        if ($tripId <= 0 || $studentId <= 0) {

            sendJSON(['success' => false, 'message' => 'trip and id are required']);

            return;

        }



        $conn = getDBConnection();



        // Load student (don't restrict by church yet — collaboration may allow cross-church updates)

        $stmt = $conn->prepare("SELECT id, name, church_id, trip_points FROM students WHERE id = ? LIMIT 1");

        $stmt->bind_param('i', $studentId);

        $stmt->execute();

        $res = $stmt->get_result();

        if ($res->num_rows === 0) {

            sendJSON(['success' => false, 'message' => 'Student not found in this church']);

            return;

        }

        $student = $res->fetch_assoc();



        // Load trip to check collaborating churches

        $tstmt = $conn->prepare("SELECT id, church_id, points_config, collaborating_churches FROM trips WHERE id = ? LIMIT 1");

        $tstmt->bind_param('i', $tripId);

        $tstmt->execute();

        $tres = $tstmt->get_result();

        if ($tres->num_rows === 0) {

            sendJSON(['success' => false, 'message' => 'Trip not found']);

            return;

        }

        $trip = $tres->fetch_assoc();



        $pointsJson = $student['trip_points'] ?? '';

        // Determine participating churches for this trip (owner + collaborators)

        $participants = [$trip['church_id']];

        $collabRaw = $trip['collaborating_churches'] ?? '';

        $collab = [];

        if (!empty($collabRaw)) {

            $decodedCollab = json_decode($collabRaw, true);

            if (is_array($decodedCollab))

                $collab = array_map('intval', $decodedCollab);

        }

        $participants = array_unique(array_merge($participants, $collab));



        if ($churchId <= 0 || !in_array($churchId, $participants, true)) {

            sendJSON(['success' => false, 'message' => 'غير مصرح بتحديث نقاط هذه الرحلة']);

            return;

        }

        $studentChurchId = intval($student['church_id'] ?? 0);

        if (!in_array($studentChurchId, $participants, true)) {

            sendJSON(['success' => false, 'message' => 'هذا الطفل غير مسجل في رحلة مشتركة مع كنيستك']);

            return;

        }

        $points = json_decode($pointsJson, true);

        if (!is_array($points))

            $points = [];



        $current = intval($points[$tripId] ?? 0);



        if ($game_action === 'increment') {

            $new = $current + $amount;

            $verb = "+{$amount}";

        } elseif ($game_action === 'decrement') {

            $new = $current - $amount;

            $verb = "-{$amount}";

        } elseif ($game_action === 'set') {

            $new = $amount;

            $verb = "={$amount}";

        } elseif ($game_action === 'naughty') {

            $isNaughty = !($points["n_{$tripId}"] ?? false);

            $points["n_{$tripId}"] = $isNaughty;

            $new = $current;

            $verb = $isNaughty ? "naughty_on" : "naughty_off";

        } else {

            sendJSON(['success' => false, 'message' => 'Invalid action']);

            return;

        }



        $points[$tripId] = $new;

        $newJson = json_encode($points, JSON_UNESCAPED_UNICODE);



        // Update student trip_points by id (no church constraint because student may belong to another church)

        $up = $conn->prepare("UPDATE students SET trip_points = ? WHERE id = ?");

        $up->bind_param('si', $newJson, $studentId);

        if (!$up->execute()) {

            sendJSON(['success' => false, 'message' => 'Failed to update points: ' . $up->error]);

            return;

        }



        // Log activity

        $details = "trip_id:$tripId;student_id:$studentId;change:$verb;new:$new";

        logActivity($churchId, $uncleId, 'game_points', $details);



        // Notify admin via existing Google Apps Script path (keeps previous behaviour)

        // If you prefer server-side email, replace sendAsyncRequest with mail logic.

        try {

            $churchEmail = getChurchAdminEmail($churchId);

            $uncleEmail = null;

            if ($uncleId) {

                $u = $conn->prepare("SELECT email FROM uncles WHERE id = ? LIMIT 1");

                $u->bind_param('i', $uncleId);

                $u->execute();

                $ur = $u->get_result()->fetch_assoc();

                $uncleEmail = $ur['email'] ?? null;

            }



            if ($churchEmail || $uncleEmail) {

                $scriptUrl = 'https://script.google.com/macros/s/AKfycbxsDA0veJTA3C_2Bw47coffOagRigWwaZnyxWuGb_gSVUCWM958V1bUcaZDwfIHVZ7b1g/exec';

                $payload = [

                    'action' => 'notifyGameActivity',

                    'church_email' => $churchEmail,

                    'uncle_email' => $uncleEmail,

                    'church_id' => $churchId,

                    'trip_id' => $tripId,

                    'student_id' => $studentId,

                    'student_name' => $student['name'] ?? '',

                    'change' => $verb,

                    'new_points' => $new,

                    'timestamp' => date('Y-m-d H:i:s')

                ];

                sendAsyncRequest($scriptUrl, $payload);

            }

        } catch (Exception $e) {

            error_log('notifyGameActivity error: ' . $e->getMessage());

        }



        $isNaughty = $points["n_{$tripId}"] ?? false;

        sendJSON(['success' => true, 'student_id' => $studentId, 'trip_id' => $tripId, 'points' => $new, 'is_naughty' => $isNaughty]);



    } catch (Exception $e) {

        error_log('processGameQRCode error: ' . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);

    }

}



function updateTripPointsConfig()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $tripId = intval($_POST['trip_id'] ?? 0);

        $configJson = $_POST['config_json'] ?? '';



        if ($tripId <= 0 || empty($configJson)) {

            sendJSON(['success' => false, 'message' => 'trip_id and config_json are required']);

            return;

        }



        // Validate JSON

        $decoded = json_decode($configJson, true);

        if (!is_array($decoded)) {

            sendJSON(['success' => false, 'message' => 'config_json must be valid JSON']);

            return;

        }



        $conn = getDBConnection();

        $stmt = $conn->prepare("UPDATE trips SET points_config = ? WHERE id = ? AND church_id = ?");

        $stmt->bind_param('sii', $configJson, $tripId, $churchId);

        if ($stmt->execute()) {

            logActivity($churchId, $_SESSION['uncle_id'] ?? null, 'update_trip_points_config', "trip:$tripId");

            sendJSON(['success' => true, 'message' => 'Trip points config saved']);

        } else {

            sendJSON(['success' => false, 'message' => 'Failed to save config: ' . $stmt->error]);

        }



    } catch (Exception $e) {

        error_log('updateTripPointsConfig error: ' . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);

    }

}



ini_set('session.gc_probability', 1);

ini_set('session.gc_divisor', 100);

ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 365 * 10);

ini_set('memory_limit', '256M');

set_time_limit(60);



// Robust local session directory to prevent aggressive shared hosting garbage collection

$rootPath = dirname(__FILE__);

while ($rootPath && !file_exists($rootPath . '/api.php')) {

    $parent = dirname($rootPath);

    if ($parent === $rootPath)

        break;

    $rootPath = $parent;

}

$sessionPath = $rootPath . '/.sessions';

if (!is_dir($sessionPath)) {

    @mkdir($sessionPath, 0755, true);

}

if (is_writable($sessionPath)) {

    session_save_path($sessionPath);

}



ini_set('session.gc_maxlifetime', 315360000);

ini_set('session.cookie_lifetime', 315360000);

session_start();



require_once 'config.php';

require_once 'audit.php';



// ── Auto-Gender Detection & Migration ─────────────────────────────

function formatStudentGenderLabel($gender)

{

    return ($gender === 'female') ? 'بنت' : 'ولد';

}



function detectGenderFromName($name) {

    $name = trim($name);

    if (empty($name)) {

        return 'male';

    }

    

    // Normalize Arabic letters

    $normalized = str_replace(

        ['أ', 'إ', 'آ', 'ى', 'ة'],

        ['ا', 'ا', 'ا', 'ي', 'ه'],

        $name

    );

    

    $parts = explode(' ', $normalized);

    $first = $parts[0];

    

    // Check compound name prefixes

    if (($first === 'عبد' || $first === 'ابو') && count($parts) > 1) {

        $first = $first . ' ' . $parts[1];

    }

    

    // Exact male list

    $maleList = [

        'مينا', 'بولا', 'توما', 'شنوده', 'بيشوي', 'كيرلس', 'جرجس', 'ابانوب', 'فادي', 'وسيم', 'رامي', 'شادي',

        'اندرو', 'مايكل', 'مارك', 'توني', 'جون', 'بول', 'بيتر', 'يوسف', 'ديفيد', 'ديڤيد', 'جورج', 'سامح', 'سامي',

        'عماد', 'ايهاب', 'هاني', 'ماريو', 'امجد', 'انطون', 'انطوني', 'بطرس', 'يوحنا', 'سليمان', 'ايليا', 'موسى',

        'عيسى', 'يحيى', 'زكريا', 'رضا', 'علاء', 'بهاء', 'كاراس', 'كراس', 'تامر', 'اشرف', 'شريف', 'عادل', 'مدحت',

        'رافت', 'نبيل', 'ايمن', 'صفوت', 'ثروت', 'مجدي', 'رمزي', 'وجدي', 'مكرم', 'حسني', 'نعيم', 'ميلاد', 'بخيت',

        'شحاته', 'refq', 'refqa', 'رفيق', 'رافي', 'ناجي', 'ناجح', 'ماهر', 'منير', 'نبيه', 'نشات', 'سعيد', 'سمير',

        'رائف', 'عريان', 'فرنسيس', 'نادر', 'ياسر', 'هشام', 'سامر', 'حنا', 'بشير', 'فاروق', 'كرم', 'امير', 'وسام',

        'باسم', 'باسل', 'ياسين', 'ابراهيم', 'اسحق', 'يعقوب', 'داود', 'دانيال', 'داني', 'غالي', 'ميشيل', 'فؤاد',

        'فريد', 'وليد', 'وجيه', 'شفيق', 'رائد', 'حازم', 'طارق', 'خالد', 'ادهم', 'مصطفى', 'وليم', 'انس', 'عمر',

        'عمرو', 'علي', 'على', 'احمد', 'محمد', 'محمود', 'كريم', 'خليل', 'ادم', 'الفريد', 'البير', 'بيير', 'فرانك',

        'جاك', 'چاك', 'باتريك', 'باترك', 'توماس', 'چوزيف', 'جوزيف', 'ستيفن', 'ستيفين', 'ستيڤن', 'كيفين', 'كيفن',

        'كيڤن', 'ماثيو', 'فلوباتير', 'مارسيل', 'ماركو', 'مارسيلينو', 'مارشيلينو', 'بافلي', 'سلوانس', 'سدرا',

        'ارساني', 'كوزمان', 'دميان', 'مكسيموس', 'ابرام', 'تادرس', 'ملاخي', 'صموئيل', 'فالنتينو', 'روماني',

        'ساروفيم', 'اسطفانوس', 'تيموثاوس', 'باسيليوس', 'اثناسيوس', 'مقار', 'باخوميوس', 'شنودة', 'صليب',

        'قزمان', 'ارميا', 'سوريال', 'ميخائيل', 'غبريال', 'رافائيل', 'روفائيل', 'اباكير', 'متى', 'مرقس',

        'لوقا', 'صفين', 'ابوسيفين', 'كوبرا', 'بولس', 'انطونيوس', 'توم', 'أوليفر', 'اوليفير', 'إيفان',

        'ايفان', 'إيمانويل', 'إيهاب', 'افرايم', 'اكيلا', 'الفريد', 'اليكس', 'ايساف', 'تيمي', 'جاستن',

        'جاستين', 'جاك', 'جو', 'جورج', 'جوزبيانو', 'جوستاف', 'جوفاني', 'جوليان', 'جوناثان', 'جوني',

        'جونير', 'جونيير', 'جيانو', 'جيسون', 'جيوفاني', 'راجي', 'راندو', 'روجيه', 'روفانيو', 'رونل',

        'روني', 'ريتشي', 'سام', 'سبيرجن', 'ستيفن', 'ستيفين', 'ستيڤن', 'عطية', 'عمانوئيل', 'فاليريو',

        'فان', 'فريد', 'فيلاديمير', 'كرستيانو', 'كريس', 'لوئيس', 'ماثيو', 'مارتن', 'مانويل', 'ماير',

        'مفدي', 'ميخائيل', 'ميرانو', 'ميلكر', 'نوفير', 'يوثام', 'يوليوس', 'چاكوب', 'چوثام', 'چوني', 'ڤيلي',

        'Amir', 'Arthur', 'Bahaa', 'Baher', 'Dan', 'Daniel', 'David', 'Ehab', 'Elie', 'Filimon', 'Jason', 'Jeff',

        'Jensen', 'Jimmy', 'Johnny', 'Jonathan', 'Justin', 'Kareem', 'Kevin', 'Manuel', 'Marvin', 'Peter',

        'Rafeek', 'Rafy', 'Ramy', 'Robin', 'Saher', 'Samuel', 'Sherif', 'Wassim', 'Youssef'

    ];

    

    if (strpos($first, 'عبد') === 0 || strpos($first, 'ابو') === 0 || strpos($first, 'امجد') === 0 || in_array($first, $maleList)) {

        return 'male';

    }

    

    // Exact female list

    $femaleList = [

        'مريم', 'ماري', 'ماريان', 'إيريني', 'ايريني', 'إيلاريا', 'aellaris', 'ايلارية', 'دميانا', 'دميانة',

        'سارة', 'ساره', 'فريدة', 'كارما', 'كرمة', 'جاكلين', 'جاكلين', 'نهى', 'رنا', 'سوزان', 'فيبي', 'فيرينا',

        'هالة', 'هاله', 'هنا', 'منار', 'روجينا', 'نورا', 'مونيكا', 'هيلين', 'ميرنا', 'سيلفيا', 'سلفيا', 'بيرلا',

        'بتول', 'رفقة', 'راحيل', 'تريفينا', 'إيفا', 'ايفا', 'استر', 'إستر', 'ليزا', 'تيا', 'روز', 'ساندرا',

        'جاسمين', 'جوليا', 'كلارا', 'هايدي', 'كارول', 'جوي', 'ريتا', 'جيسيكا', 'إيفون', 'فيفيان', 'جانيت',

        'هيلانة', 'جوليت', 'لوسيندا', 'ناردين', 'بارثينيا', 'شيرين', 'دينا', 'رانيا', 'ياسمين', 'فريده',

        'سيلينا', 'رونزا', 'كرمه', 'دانيلا', 'كلاريس', 'باتريسيا', 'أميرة', 'أليس', 'أنجلينا', 'أنجيلينا',

        'أنسطاسيا', 'أيتن', 'أوليفيا', 'انسي', 'أوجانيا', 'برستيرا', 'برسيس', 'بريتني', 'بريتى', 'بريتي',

        'بريسكلا', 'بريسكيلا', 'بيري', 'بيلا', 'ترنيم', 'جاسمين', 'جاندرك', 'جريس', 'جريسي', 'جلوري',

        'جلوريا', 'جنى', 'جوسيان', 'جوسيانا', 'جولي', 'جوليت', 'جوليسيا', 'جومانا', 'جومانة', 'جونستا',

        'جوى', 'جويس', 'جويسي', 'جيسكيا', 'جيسى', 'جيسي', 'جيسيكا', 'جيني', 'جينيفير', 'راندا', 'راندو',

        'rose', 'روز', 'روفانية', 'روفينا', 'ريما', 'ريناتا', 'سما', 'سوسنة', 'سيلفيا', 'سيلينا', 'سيمون', 'صوفي',

        'صوفيا', 'عزة', 'عزيزه', 'فرح', 'فريدة', 'فيبي', 'فيرينا', 'فيلومينا', 'كارمن', 'كارن', 'كارول',

        'كارولين', 'كارين', 'كاندي', 'كلارا', 'كلاريس', 'كوين', 'كيريا', 'لارن', 'لارين', 'لافينا', 'لورين',

        'لوسيندا', 'ليديا', 'ليلي', 'ليليان', 'لينور', 'مارسندا', 'مارفي', 'مارلي', 'مارلين', 'ماروسكا',

        'مارولين', 'ماري', 'ماريان', 'ماريتشيا', 'مارينا', 'مارڤي', 'مافي', 'مايا', 'مايفين', 'مايلي',

        'مريم', 'مهرائيل', 'مولي', 'ميرا', 'ميرال', 'ميراي', 'ميرنا', 'ميرولا', 'ميلا', 'مينورا', 'ناتالي',

        'نانسي', 'نتالي', 'نسمة', 'هولى', 'هولي', 'هيفن', 'هيفين', 'هيڤن', 'هيڤين', 'يؤانا', 'يوأنا',

        'يوانا', 'يوستينا', 'چوي', 'چويس', 'چيسى', 'چيسي', 'چيسيكا',

        'Amany', 'Amy', 'Ann', 'Asnat', 'Carol', 'Christeen', 'Christina', 'Dalia', 'Doreen', 'Eman', 'Emma',

        'Fadia', 'Grace', 'Heidi', 'Helen', 'Jakleen', 'Jasmin', 'Joy', 'Joyce', 'Julie', 'Karine', 'Liberty',

        'Lily', 'Lucy', 'Lydia', 'Madonna', 'Malika', 'Mariam', 'Marlin', 'Marly', 'Merolla', 'Nancy', 'Nardeen',

        'Nesreen', 'Nora', 'Perin', 'Priscilla', 'Remona', 'Remonda', 'Rosette', 'Sally', 'Sandra', 'Sara',

        'Sarah', 'Selena', 'Sherry', 'Sofia', 'Sylvia', 'Tant', 'Tia', 'Tota', 'Vicky'

    ];

    

    if (in_array($first, $femaleList)) {

        return 'female';

    }

    

    // Heuristic suffixes (Arabic letters normalized)

    $last_chars_3 = mb_substr($first, -3);

    $last_chars_2 = mb_substr($first, -2);

    $last_char = mb_substr($first, -1);

    

    if ($last_chars_3 === 'ينا' || $last_chars_2 === 'ين' || $last_char === 'ه' || $last_char === 'ة') {

        return 'female';

    }

    

    return 'male';

}





// Custom error handler

set_error_handler(function ($errno, $errstr, $errfile, $errline) {

    $error = "PHP Error [$errno]: $errstr in $errfile on line $errline";

    error_log($error);



    // Clean any output

    if (ob_get_length())

        ob_clean();



    echo json_encode([

        'success' => false,

        'message' => 'خطأ في PHP: ' . $errstr,

        'debug' => [

            'file' => basename($errfile),

            'line' => $errline,

            'error' => $errstr

        ]

    ]);

    exit;

});



// Custom exception handler

set_exception_handler(function ($exception) {

    error_log("Uncaught Exception: " . $exception->getMessage());



    // Clean any output

    if (ob_get_length())

        ob_clean();



    echo json_encode([

        'success' => false,

        'message' => 'خطأ: ' . $exception->getMessage(),

        'debug' => [

            'file' => basename($exception->getFile()),

            'line' => $exception->getLine()

        ]

    ]);

    exit;

});

// Error handler

function handleError($message)

{

    sendJSON(['success' => false, 'message' => $message]);

}



// ── Image Enhancement Function ──────────────────────────────

function enhanceImage($imagePath, $targetWidth = 400, $targetHeight = 500)

{

    // Load image

    $imageInfo = @getimagesize($imagePath);

    if (!$imageInfo) {

        return null;

    }



    $mime = $imageInfo['mime'];

    $source = null;



    // Create image resource based on type

    if ($mime === 'image/jpeg') {

        $source = @imagecreatefromjpeg($imagePath);

    } elseif ($mime === 'image/png') {

        $source = @imagecreatefrompng($imagePath);

    } elseif ($mime === 'image/gif') {

        $source = @imagecreatefromgif($imagePath);

    } elseif ($mime === 'image/webp') {

        $source = @imagecreatefromwebp($imagePath);

    }



    if (!$source) {

        return null;

    }



    $width = imagesx($source);

    $height = imagesy($source);



    // Calculate new dimensions maintaining aspect ratio

    $ratio = $width / $height;

    $targetRatio = $targetWidth / $targetHeight;



    if ($ratio > $targetRatio) {

        $newWidth = $targetHeight * $ratio;

        $newHeight = $targetHeight;

    } else {

        $newWidth = $targetWidth;

        $newHeight = $targetWidth / $ratio;

    }



    // Create intermediate image (scaled)

    $scaled = imagecreatetruecolor($newWidth, $newHeight);

    imagealphablending($scaled, false);

    imagesavealpha($scaled, true);

    imagecopyresampled($scaled, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);



    // Create final image with cropping (center crop)

    $final = imagecreatetruecolor($targetWidth, $targetHeight);

    imagealphablending($final, false);

    imagesavealpha($final, true);



    $offsetX = ($newWidth - $targetWidth) / 2;

    $offsetY = ($newHeight - $targetHeight) / 2;



    imagecopy($final, $scaled, 0, 0, $offsetX, $offsetY, $targetWidth, $targetHeight);



    // Apply enhancement: increase contrast and brightness

    imagefilter($final, IMG_FILTER_CONTRAST, 5);

    imagefilter($final, IMG_FILTER_BRIGHTNESS, 15);

    imagefilter($final, IMG_FILTER_SMOOTH, 1);



    imagedestroy($source);

    imagedestroy($scaled);



    return $final;

}



// ── Trip access helpers ─────────────────────────────────────

function getTripParticipantIds($tripRow)

{

    $participants = [intval($tripRow['church_id'] ?? 0)];

    $collabRaw = $tripRow['collaborating_churches'] ?? '';

    if (!empty($collabRaw)) {

        $collab = json_decode($collabRaw, true);

        if (is_array($collab)) {

            $participants = array_unique(array_merge($participants, array_map('intval', $collab)));

        }

    }

    return array_values(array_filter($participants, function ($id) {

        return $id > 0;

    }));

}



function isTripDeveloperViewerRole()

{

    $role = strtolower($_SESSION['uncle_role'] ?? '');

    return in_array($role, ['developer', 'dev', 'admin', 'administrator'], true);

}



/** Bypass trip ACL only when a developer explicitly switches church context. */

function isTripDeveloperViewer()

{

    if (!isTripDeveloperViewerRole()) {

        return false;

    }

    return !empty($_POST['dev_override_church_id']) || !empty($_GET['dev_override_church_id']);

}



function churchIsTripParticipant($tripRow, $churchId)

{

    return in_array(intval($churchId), getTripParticipantIds($tripRow), true);

}



function verifyTripParticipant($conn, $tripId, $churchId)

{

    $stmt = $conn->prepare("SELECT church_id, collaborating_churches FROM trips WHERE id = ?");

    $stmt->bind_param("i", $tripId);

    $stmt->execute();

    $res = $stmt->get_result()->fetch_assoc();

    if (!$res) {

        return false;

    }

    return churchIsTripParticipant($res, $churchId);

}



function churchHasPendingTripAccessRequest($conn, $tripId, $requesterChurchId, $ownerChurchId)

{

    ensureTripCollaborationRequestsTable($conn);

    $chk = $conn->prepare("SELECT id FROM trip_collaboration_requests WHERE trip_id = ? AND from_church_id = ? AND to_church_id = ? AND status = 'pending' LIMIT 1");

    $chk->bind_param('iii', $tripId, $requesterChurchId, $ownerChurchId);

    $chk->execute();

    return $chk->get_result()->num_rows > 0;

}



function buildTripAccessDeniedResponse($conn, $trip, $churchId)

{

    $ownerChurchId = intval($trip['church_id']);

    $ownerName = '';

    $cStmt = $conn->prepare("SELECT church_name FROM churches WHERE id = ? LIMIT 1");

    if ($cStmt) {

        $cStmt->bind_param('i', $ownerChurchId);

        $cStmt->execute();

        $crow = $cStmt->get_result()->fetch_assoc();

        $ownerName = $crow['church_name'] ?? '';

    }



    $pending = ($churchId > 0) ? churchHasPendingTripAccessRequest($conn, intval($trip['id']), $churchId, $ownerChurchId) : false;



    sendJSON([

        'success' => false,

        'access_denied' => true,

        'message' => 'ليس لديك صلاحية لعرض هذه الرحلة',

        'trip' => [

            'id' => intval($trip['id']),

            'title' => $trip['title'] ?? '',

            'image_url' => $trip['image_url'] ?? '',

            'owner_church_id' => $ownerChurchId,

            'owner_church_name' => $ownerName,

        ],

        'pending_request' => $pending,

    ]);

}



// ── Request trip access (replaces instant join) ─────────────

function requestTripAccess()

{

    try {

        checkUncleAuth();

        $churchId = getChurchId();

        if ($churchId <= 0) {

            sendJSON(['success' => false, 'message' => 'يجب تسجيل الدخول ككنيسة لطلب المشاركة في الرحلة']);

            return;

        }



        $tripId = intval($_POST['trip_id'] ?? $_GET['trip_id'] ?? 0);

        if ($tripId <= 0) {

            sendJSON(['success' => false, 'message' => 'معرف الرحلة مطلوب']);

            return;

        }



        $conn = getDBConnection();

        ensureTripCollaborationRequestsTable($conn);



        $stmt = $conn->prepare("SELECT id, church_id, collaborating_churches, title FROM trips WHERE id = ? LIMIT 1");

        $stmt->bind_param('i', $tripId);

        $stmt->execute();

        $res = $stmt->get_result();

        if ($res->num_rows === 0) {

            sendJSON(['success' => false, 'message' => 'الرحلة غير موجودة']);

            return;

        }



        $row = $res->fetch_assoc();

        $tripTitle = $row['title'] ?? '';

        $ownerChurchId = intval($row['church_id']);



        if ($ownerChurchId === $churchId) {

            sendJSON(['success' => true, 'message' => 'هذه الكنيسة هي مالكة الرحلة', 'trip_id' => $tripId, 'already_joined' => true]);

            return;

        }



        if (in_array($churchId, getTripParticipantIds($row), true)) {

            sendJSON([

                'success' => true,

                'message' => 'كنيستك تشارك بالفعل في هذه الرحلة',

                'trip_id' => $tripId,

                'trip_title' => $tripTitle,

                'already_joined' => true,

            ]);

            return;

        }



        if (churchHasPendingTripAccessRequest($conn, $tripId, $churchId, $ownerChurchId)) {

            sendJSON([

                'success' => true,

                'message' => 'تم إرسال طلب المشاركة مسبقاً وهو قيد المراجعة',

                'trip_id' => $tripId,

                'trip_title' => $tripTitle,

                'pending_request' => true,

            ]);

            return;

        }



        $ins = $conn->prepare("INSERT INTO trip_collaboration_requests (trip_id, from_church_id, to_church_id, status) VALUES (?, ?, ?, 'pending')");

        $ins->bind_param('iii', $tripId, $churchId, $ownerChurchId);

        if ($ins->execute()) {

            logActivity($churchId, $_SESSION['uncle_id'] ?? null, 'request_trip_access', "trip:$tripId");

            $ownerName = '';

            $cStmt = $conn->prepare("SELECT church_name FROM churches WHERE id = ? LIMIT 1");

            if ($cStmt) {

                $cStmt->bind_param('i', $ownerChurchId);

                $cStmt->execute();

                $crow = $cStmt->get_result()->fetch_assoc();

                $ownerName = $crow['church_name'] ?? '';

            }

            sendJSON([

                'success' => true,

                'message' => 'تم إرسال طلب المشاركة إلى ' . ($ownerName ?: 'كنيسة مالكة الرحلة'),

                'trip_id' => $tripId,

                'trip_title' => $tripTitle,

                'pending_request' => true,

            ]);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل إرسال طلب المشاركة: ' . $ins->error]);

        }

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => 'خطأ في طلب المشاركة: ' . $e->getMessage()]);

    }

}



function joinTrip()

{

    requestTripAccess();

}



// ── Collaboration Helpers ──────────────────────────────────

function verifyRegistrationParticipant($conn, $registrationId, $churchId)

{

    $stmt = $conn->prepare("

        SELECT t.church_id, t.collaborating_churches

        FROM trip_registrations tr

        JOIN trips t ON tr.trip_id = t.id

        WHERE tr.id = ?

    ");

    $stmt->bind_param("i", $registrationId);

    $stmt->execute();

    $res = $stmt->get_result()->fetch_assoc();

    if (!$res)

        return false;

    $participants = [intval($res['church_id'])];

    $collabRaw = $res['collaborating_churches'] ?? '';

    if (!empty($collabRaw)) {

        $collab = json_decode($collabRaw, true);

        if (is_array($collab)) {

            $participants = array_unique(array_merge($participants, array_map('intval', $collab)));

        }

    }

    return in_array(intval($churchId), $participants);

}



function getCollaboratingChurchesList($conn, $collabRaw, $ownerChurchId = 0)

{

    $ids = [];

    if (!empty($collabRaw)) {

        $decoded = json_decode($collabRaw, true);

        if (is_array($decoded)) {

            $ids = array_values(array_unique(array_filter(array_map('intval', $decoded), function ($id) use ($ownerChurchId) {

                return $id > 0 && $id !== intval($ownerChurchId);

            })));

        }

    }

    if (empty($ids)) {

        return [];

    }



    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $types = str_repeat('i', count($ids));

    $stmt = $conn->prepare("SELECT id, church_name, church_code FROM churches WHERE id IN ($placeholders)");

    if (!$stmt) {

        return [];

    }

    $stmt->bind_param($types, ...$ids);

    $stmt->execute();

    $result = $stmt->get_result();

    $byId = [];

    while ($row = $result->fetch_assoc()) {

        $byId[intval($row['id'])] = [

            'id' => intval($row['id']),

            'name' => $row['church_name'],

            'code' => $row['church_code'],

        ];

    }



    $list = [];

    foreach ($ids as $id) {

        if (isset($byId[$id])) {

            $list[] = $byId[$id];

        }

    }

    return $list;

}



function removeTripCollaborator()

{

    try {

        checkUncleAuth();

        $churchId = getChurchId();

        if ($churchId <= 0) {

            sendJSON(['success' => false, 'message' => 'يجب تسجيل الدخول ككنيسة']);

            return;

        }



        $tripId = intval($_POST['trip_id'] ?? 0);

        $removeChurchId = intval($_POST['collaborator_church_id'] ?? 0);

        if ($tripId <= 0 || $removeChurchId <= 0) {

            sendJSON(['success' => false, 'message' => 'بيانات غير صحيحة']);

            return;

        }



        $conn = getDBConnection();

        $tStmt = $conn->prepare("SELECT church_id, collaborating_churches, title FROM trips WHERE id = ? LIMIT 1");

        $tStmt->bind_param('i', $tripId);

        $tStmt->execute();

        $trip = $tStmt->get_result()->fetch_assoc();

        if (!$trip) {

            sendJSON(['success' => false, 'message' => 'الرحلة غير موجودة']);

            return;

        }

        if (intval($trip['church_id']) !== $churchId) {

            sendJSON(['success' => false, 'message' => 'فقط مالك الرحلة يمكنه إزالة كنيسة من المشاركة']);

            return;

        }

        if ($removeChurchId === intval($trip['church_id'])) {

            sendJSON(['success' => false, 'message' => 'لا يمكن إزالة كنيسة مالكة الرحلة']);

            return;

        }



        $collab = [];

        if (!empty($trip['collaborating_churches'])) {

            $decoded = json_decode($trip['collaborating_churches'], true);

            if (is_array($decoded)) {

                $collab = array_values(array_unique(array_map('intval', $decoded)));

            }

        }

        if (!in_array($removeChurchId, $collab, true)) {

            sendJSON(['success' => false, 'message' => 'هذه الكنيسة غير مسجلة كشريكة في الرحلة']);

            return;

        }



        $collab = array_values(array_filter($collab, fn($id) => $id !== $removeChurchId));

        $collabJson = json_encode($collab, JSON_UNESCAPED_UNICODE);

        $up = $conn->prepare("UPDATE trips SET collaborating_churches = ? WHERE id = ?");

        $up->bind_param('si', $collabJson, $tripId);

        if (!$up->execute()) {

            sendJSON(['success' => false, 'message' => 'فشل تحديث بيانات الرحلة']);

            return;

        }



        ensureTripCollaborationRequestsTable($conn);

        $delReq = $conn->prepare("DELETE FROM trip_collaboration_requests WHERE trip_id = ? AND (from_church_id = ? OR to_church_id = ?)");

        if ($delReq) {

            $delReq->bind_param('iii', $tripId, $removeChurchId, $removeChurchId);

            $delReq->execute();

        }



        $removedName = '';

        $nameStmt = $conn->prepare("SELECT church_name FROM churches WHERE id = ? LIMIT 1");

        if ($nameStmt) {

            $nameStmt->bind_param('i', $removeChurchId);

            $nameStmt->execute();

            $nr = $nameStmt->get_result()->fetch_assoc();

            $removedName = $nr['church_name'] ?? '';

        }



        sendJSON([

            'success' => true,

            'message' => 'تم إزالة ' . ($removedName ?: 'الكنيسة') . ' من المشاركة',

            'collaborators_list' => getCollaboratingChurchesList($conn, $collabJson, intval($trip['church_id'])),

        ]);

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);

    }

}



function ensureTripCollaborationRequestsTable($conn)

{

    $sql = "CREATE TABLE IF NOT EXISTS `trip_collaboration_requests` (

        `id` int(11) NOT NULL AUTO_INCREMENT,

        `trip_id` int(11) NOT NULL,

        `from_church_id` int(11) NOT NULL,

        `to_church_id` int(11) NOT NULL,

        `status` enum('pending','accepted','rejected') NOT NULL DEFAULT 'pending',

        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),

        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),

        PRIMARY KEY (`id`),

        KEY `trip_id` (`trip_id`),

        KEY `from_church_id` (`from_church_id`),

        KEY `to_church_id` (`to_church_id`)

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $conn->query($sql);

}



function sendCollaborationRequest()

{

    try {

        checkUncleAuth();

        $churchId = getChurchId();

        if ($churchId <= 0) {

            sendJSON(['success' => false, 'message' => 'يجب تسجيل الدخول ككنيسة لإرسال دعوة المشاركة']);

            return;

        }



        $tripId = intval($_POST['trip_id'] ?? 0);

        $toChurchCode = sanitize($_POST['to_church_code'] ?? '');



        if ($tripId <= 0 || empty($toChurchCode)) {

            sendJSON(['success' => false, 'message' => 'جميع الحقول مطلوبة']);

            return;

        }



        $conn = getDBConnection();

        ensureTripCollaborationRequestsTable($conn);



        // 1. Verify trip exists and user is owner

        $tStmt = $conn->prepare("SELECT church_id, collaborating_churches FROM trips WHERE id = ?");

        $tStmt->bind_param("i", $tripId);

        $tStmt->execute();

        $trip = $tStmt->get_result()->fetch_assoc();

        if (!$trip) {

            sendJSON(['success' => false, 'message' => 'الرحلة غير موجودة']);

            return;

        }

        if (intval($trip['church_id']) !== $churchId) {

            sendJSON(['success' => false, 'message' => 'غير مصرح لك بإرسال طلب مشاركة لهذه الرحلة']);

            return;

        }



        // 2. Lookup recipient church

        $cStmt = $conn->prepare("SELECT id, church_name FROM churches WHERE church_code = ? LIMIT 1");

        $cStmt->bind_param("s", $toChurchCode);

        $cStmt->execute();

        $toChurch = $cStmt->get_result()->fetch_assoc();

        if (!$toChurch) {

            sendJSON(['success' => false, 'message' => 'رمز الكنيسة غير صحيح أو الكنيسة غير موجودة']);

            return;

        }

        $toChurchId = intval($toChurch['id']);



        if ($toChurchId === $churchId) {

            sendJSON(['success' => false, 'message' => 'لا يمكنك إرسال طلب مشاركة لكنيستك']);

            return;

        }



        // 3. Check if already a collaborator

        $collab = [];

        if (!empty($trip['collaborating_churches'])) {

            $collab = json_decode($trip['collaborating_churches'], true);

            if (!is_array($collab))

                $collab = [];

        }

        if (in_array($toChurchId, $collab)) {

            sendJSON(['success' => false, 'message' => 'هذه الكنيسة تشارك بالفعل في الرحلة']);

            return;

        }



        // 4. Check for existing pending request

        $chk = $conn->prepare("SELECT id FROM trip_collaboration_requests WHERE trip_id = ? AND to_church_id = ? AND status = 'pending'");

        $chk->bind_param("ii", $tripId, $toChurchId);

        $chk->execute();

        if ($chk->get_result()->num_rows > 0) {

            sendJSON(['success' => false, 'message' => 'يوجد طلب معلق مرسل بالفعل لهذه الكنيسة']);

            return;

        }



        // 5. Insert request

        $ins = $conn->prepare("INSERT INTO trip_collaboration_requests (trip_id, from_church_id, to_church_id, status) VALUES (?, ?, ?, 'pending')");

        $ins->bind_param("iii", $tripId, $churchId, $toChurchId);

        if ($ins->execute()) {

            sendJSON(['success' => true, 'message' => 'تم إرسال طلب المشاركة بنجاح إلى ' . $toChurch['church_name']]);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل إرسال الطلب: ' . $ins->error]);

        }

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);

    }

}



function getCollaborationRequests()

{

    try {

        $churchId = getChurchId();

        $conn = getDBConnection();

        ensureTripCollaborationRequestsTable($conn);



        // Incoming requests (to this church)

        $inStmt = $conn->prepare("

            SELECT r.*, t.title as trip_title, t.church_id as trip_owner_church_id,

                   c.church_name as from_church_name 

            FROM trip_collaboration_requests r 

            JOIN trips t ON r.trip_id = t.id 

            JOIN churches c ON r.from_church_id = c.id 

            WHERE r.to_church_id = ? AND r.status = 'pending'

            ORDER BY r.created_at DESC

        ");

        $inStmt->bind_param("i", $churchId);

        $inStmt->execute();

        $incoming = $inStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($incoming as &$row) {

            $row['request_type'] = (intval($row['from_church_id']) === intval($row['trip_owner_church_id']))

                ? 'invite' : 'access_request';

        }

        unset($row);



        // Outgoing requests (from this church)

        $outStmt = $conn->prepare("

            SELECT r.*, t.title as trip_title, c.church_name as to_church_name 

            FROM trip_collaboration_requests r 

            JOIN trips t ON r.trip_id = t.id 

            JOIN churches c ON r.to_church_id = c.id 

            WHERE r.from_church_id = ?

            ORDER BY r.created_at DESC

        ");

        $outStmt->bind_param("i", $churchId);

        $outStmt->execute();

        $outgoing = $outStmt->get_result()->fetch_all(MYSQLI_ASSOC);



        sendJSON(['success' => true, 'incoming' => $incoming, 'outgoing' => $outgoing]);

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);

    }

}



function respondToCollaborationRequest()

{

    try {

        $churchId = getChurchId();

        $requestId = intval($_POST['request_id'] ?? 0);

        $status = sanitize($_POST['status'] ?? ''); // 'accepted' or 'rejected'



        if ($requestId <= 0 || !in_array($status, ['accepted', 'rejected'])) {

            sendJSON(['success' => false, 'message' => 'بيانات غير صحيحة']);

            return;

        }



        $conn = getDBConnection();

        ensureTripCollaborationRequestsTable($conn);



        // Verify request belongs to the logged-in church

        $rStmt = $conn->prepare("SELECT * FROM trip_collaboration_requests WHERE id = ? AND to_church_id = ? AND status = 'pending' LIMIT 1");

        $rStmt->bind_param("ii", $requestId, $churchId);

        $rStmt->execute();

        $request = $rStmt->get_result()->fetch_assoc();

        if (!$request) {

            sendJSON(['success' => false, 'message' => 'الطلب غير موجود أو تمت معالجته بالفعل']);

            return;

        }



        $tripId = intval($request['trip_id']);



        if ($status === 'accepted') {

            $tStmt = $conn->prepare("SELECT church_id, collaborating_churches FROM trips WHERE id = ?");

            $tStmt->bind_param("i", $tripId);

            $tStmt->execute();

            $trip = $tStmt->get_result()->fetch_assoc();

            if (!$trip) {

                sendJSON(['success' => false, 'message' => 'الرحلة المرتبطة بالطلب لم تعد موجودة']);

                return;

            }



            $ownerChurchId = intval($trip['church_id']);

            // Church joining the trip is the non-owner side of the request

            $joinChurchId = (intval($request['from_church_id']) === $ownerChurchId)

                ? intval($request['to_church_id'])

                : intval($request['from_church_id']);



            if ($joinChurchId <= 0 || $joinChurchId === $ownerChurchId) {

                sendJSON(['success' => false, 'message' => 'بيانات الطلب غير صالحة']);

                return;

            }



            $collab = [];

            if (!empty($trip['collaborating_churches'])) {

                $collab = json_decode($trip['collaborating_churches'], true);

                if (!is_array($collab))

                    $collab = [];

            }

            if (!in_array($joinChurchId, array_map('intval', $collab), true)) {

                $collab[] = $joinChurchId;

            }

            $collab = array_values(array_unique(array_map('intval', $collab)));

            $collabJson = json_encode($collab, JSON_UNESCAPED_UNICODE);



            $upTrip = $conn->prepare("UPDATE trips SET collaborating_churches = ? WHERE id = ?");

            $upTrip->bind_param("si", $collabJson, $tripId);

            if (!$upTrip->execute()) {

                sendJSON(['success' => false, 'message' => 'فشل تحديث بيانات الرحلة']);

                return;

            }

        }



        // Update request status

        $upReq = $conn->prepare("UPDATE trip_collaboration_requests SET status = ? WHERE id = ?");

        $upReq->bind_param("si", $status, $requestId);

        if ($upReq->execute()) {

            sendJSON(['success' => true, 'message' => $status === 'accepted' ? 'تم قبول طلب المشاركة بنجاح' : 'تم رفض الطلب']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل تحديث الطلب']);

        }

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);

    }

}



// ── Get Trip Students (for QR export) ──────────────────────────

function getTripStudents()

{

    try {

        $tripId = intval($_POST['trip_id'] ?? $_GET['trip_id'] ?? 0);

        if ($tripId <= 0) {

            sendJSON(['success' => false, 'message' => 'trip_id is required']);

            return;

        }

        $conn = getDBConnection();



        $churchId = getChurchId();

        if (!verifyTripParticipant($conn, $tripId, $churchId)) {

            sendJSON(['success' => false, 'message' => 'غير مصرح لك بعرض طلاب هذه الرحلة']);

            return;

        }



        // Get trip info

        $tStmt = $conn->prepare("SELECT id, title, church_id, points_config FROM trips WHERE id = ? LIMIT 1");

        $tStmt->bind_param('i', $tripId);

        $tStmt->execute();

        $tRes = $tStmt->get_result();

        if ($tRes->num_rows === 0) {

            sendJSON(['success' => false, 'message' => 'Trip not found']);

            return;

        }

        $trip = $tRes->fetch_assoc();



        // Get all students in this trip (from trip_registrations)

        $sStmt = $conn->prepare("

            SELECT DISTINCT s.id, s.name, s.church_id, s.gender, s.birthday,

                   COALESCE(cc.arabic_name, cl.arabic_name, s.class) AS class,

                   c.name AS church_name

            FROM trip_registrations tr

            JOIN students s ON s.id = tr.student_id

            LEFT JOIN church_classes cc ON cc.id = s.class_id

            LEFT JOIN classes cl ON cl.id = s.class_id

            LEFT JOIN churches c ON c.id = s.church_id

            WHERE tr.trip_id = ? AND tr.cancelled = 0

            ORDER BY s.name

        ");

        $sStmt->bind_param('i', $tripId);

        $sStmt->execute();

        $students = $sStmt->get_result()->fetch_all(MYSQLI_ASSOC);



        // Attach points for each student

        foreach ($students as &$s) {

            $pStmt = $conn->prepare("SELECT trip_points FROM students WHERE id = ? LIMIT 1");

            $pStmt->bind_param('i', $s['id']);

            $pStmt->execute();

            $pRes = $pStmt->get_result()->fetch_assoc();

            $tp = $pRes['trip_points'] ?? '';

            $pointsMap = json_decode($tp, true);

            if (!is_array($pointsMap))

                $pointsMap = [];

            $s['points'] = intval($pointsMap[$tripId] ?? 0);

            $s['is_naughty'] = $pointsMap["n_{$tripId}"] ?? false;

        }



        sendJSON(['success' => true, 'trip' => $trip, 'students' => $students]);

    } catch (Exception $e) {

        error_log('getTripStudents error: ' . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);

    }

}



// ── Get Trip Template (custom columns config) ──────────────────────────

function getTripTemplate()

{

    try {

        $tripId = intval($_POST['trip_id'] ?? $_GET['trip_id'] ?? 0);

        if ($tripId <= 0) {

            sendJSON(['success' => false, 'message' => 'trip_id is required']);

            return;

        }

        $conn = getDBConnection();

        $stmt = $conn->prepare("SELECT points_config FROM trips WHERE id = ? LIMIT 1");

        $stmt->bind_param('i', $tripId);

        $stmt->execute();

        $res = $stmt->get_result();

        if ($res->num_rows === 0) {

            sendJSON(['success' => false, 'message' => 'Trip not found']);

            return;

        }

        $row = $res->fetch_assoc();

        $config = [];

        if (!empty($row['points_config'])) {

            $decoded = json_decode($row['points_config'], true);

            if (is_array($decoded))

                $config = $decoded;

        }

        // Default columns if none saved

        $defaultColumns = ['Points', 'QrCode', 'Name', 'Church', 'Class', 'Gender', 'Age'];

        $template = $config ?: ['columns' => $defaultColumns];



        sendJSON(['success' => true, 'template' => $template, 'available_columns' => ['Points', 'QrCode', 'Name', 'Church', 'Class', 'Gender', 'Age', 'Room', 'Building', 'Team', 'Team No']]);

    } catch (Exception $e) {

        error_log('getTripTemplate error: ' . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);

    }

}



// ── Save enhanced image ──────────────────────────────────────

function saveEnhancedImage($image, $outputPath, $quality = 85)

{

    $ext = strtolower(pathinfo($outputPath, PATHINFO_EXTENSION));



    if ($ext === 'jpg' || $ext === 'jpeg') {

        return imagejpeg($image, $outputPath, $quality);

    } elseif ($ext === 'png') {

        return imagepng($image, $outputPath, 8);

    } elseif ($ext === 'gif') {

        return imagegif($image, $outputPath);

    } elseif ($ext === 'webp') {

        return imagewebp($image, $outputPath, $quality);

    }



    return imagejpeg($image, $outputPath, $quality);

}



// Check if user is logged in

function checkAuth()

{

    if (isset($_SESSION['uncle_role']) && in_array(strtolower($_SESSION['uncle_role']), ['developer', 'dev'])) {

        return;

    }

    if (!isset($_SESSION['church_id'])) {

        sendJSON(['success' => false, 'message' => 'غير مصرح - الرجاء تسجيل الدخول']);

    }

}

function checkUncleAuth()

{

    // Accept uncle session OR church admin session

    if (!isset($_SESSION['uncle_id']) && !isset($_SESSION['church_id'])) {

        sendJSON(['success' => false, 'message' => 'غير مصرح - الرجاء تسجيل الدخول']);

    }

}

// Get church ID from session



function getChurchId()

{

    // 1. Developer override — sent explicitly by the JS church-switcher

    //    This takes priority over session so devs can view any church.

    if (!empty($_POST['dev_override_church_id'])) {

        $override = intval($_POST['dev_override_church_id']);

        if ($override > 0) {

            // Verify caller is actually a developer

            $callerRole = $_SESSION['uncle_role'] ?? '';

            if (in_array(strtolower($callerRole), ['developer', 'dev', 'admin', 'administrator'])) {

                error_log("getChurchId - dev override: $override (caller role: $callerRole)");

                return $override;

            }

        }

    }



    // 2. Session — normal path for both church and uncle logins

    if (isset($_SESSION['church_id']) && !empty($_SESSION['church_id'])) {

        return intval($_SESSION['church_id']);

    }



    // 3. POST church_id — fallback for cases where session is missing

    if (isset($_POST['church_id']) && !empty($_POST['church_id'])) {

        return intval($_POST['church_id']);

    }



    // 4. GET church_id

    if (isset($_GET['church_id']) && !empty($_GET['church_id'])) {

        return intval($_GET['church_id']);

    }



    error_log("getChurchId - no church_id found. Session: " . json_encode($_SESSION));

    return 0;

}

// Get action from request

$action = '';

if (isset($_GET['action'])) {

    $action = $_GET['action'];

} elseif (isset($_POST['action'])) {

    $action = $_POST['action'];

}



// Log the action for debugging

error_log("API Action: " . $action);



// Handle different actions

try {

    switch ($action) {

        case 'getAuditLogs':

            checkAuth();

            getAuditLogs();

            break;



        case 'getEntityAuditHistory':

            checkAuth();

            getEntityAuditHistory();

            break;



        case 'deleteAuditLog':

            checkAuth();

            deleteAuditLog();

            break;



        case 'clearAllAuditLogs':

            checkAuth();

            clearAllAuditLogs();

            break;



        case 'joinTrip':

            checkAuth();

            joinTrip();

            break;



        case 'requestTripAccess':

            checkAuth();

            requestTripAccess();

            break;



        case 'getTripStudents':

            getTripStudents();

            break;



        case 'getTripTemplate':

            getTripTemplate();

            break;



        case 'getUncleActivityLogs':

            checkUncleAuth();

            getUncleActivityLogs();

            break;



        // ── Church Registration Keys ──────────────────────────────

        case 'generateChurchRegKey':

            checkAuth();

            generateChurchRegKey();

            break;



        case 'listChurchRegKeys':

            checkAuth();

            listChurchRegKeys();

            break;



        case 'revokeChurchRegKey':

            checkAuth();

            revokeChurchRegKey();

            break;



        case 'validateChurchRegKey':

            validateChurchRegKey();

            break;



        case 'createChurchWithAdmin':

            createChurchWithAdmin();

            break;



        // ── Uncle Registration (plain church code link) ─────────────

        case 'registerUncleWithChurchCode':

            registerUncleWithChurchCode();

            break;



        case 'getPublicChurchInfo':

            getPublicChurchInfo();

            break;



        case 'checkRegistrationStatus':

            checkRegistrationStatus();

            break;

        case 'saveSiblingGroup':

            saveSiblingGroup();

            break;

        case 'searchAllStudents':

            searchAllStudents();

            break;



        case 'auto_login':  // أضف هذا

            handleAutoLogin();

            break;

        case 'verifyChurchPassword':

            verifyChurchPassword();

            break;





        case 'logout':

            session_destroy();

            sendJSON(['success' => true, 'message' => 'تم تسجيل الخروج بنجاح']);

            break;



        case 'getData':

            checkAuth();

            getData();

            break;



        case 'submitAttendance':

            checkAuth();

            submitAttendance();

            break;



        case 'updateCoupons':

            checkAuth();

            updateCoupons();

            break;



        case 'addStudent':

            checkAuth();

            addStudent();

            break;



        case 'updateStudent':

            checkAuth();

            updateStudent();

            break;



        case 'uncleLogin':

            handleUncleLogin();

            break;



        case 'getCurrentUncle':

            getCurrentUncle();

            break;



        case 'updateUncleProfile':

            updateUncleProfile();

            break;



        case 'updateUncleImage':

            updateUncleImage();

            break;



        case 'getAllUncles':

            getAllUncles();

            break;



        case 'addUncle':

            addUncle();

            break;



        case 'updateUncle':

            updateUncle();

            break;



        case 'deleteUncle':

            deleteUncle();

            break;



        case 'updateChurchAdminEmail':

            updateChurchAdminEmail();

            break;



        case 'deleteStudent':

            checkAuth();

            deleteStudent();

            break;



        case 'updateStudentImage':

            checkAuth();

            updateStudentImage();

            break;



        case 'getAllAnnouncements':

            checkAuth();

            getAllAnnouncements();

            break;



        case 'addAnnouncement':

            checkAuth();

            addAnnouncement();

            break;



        case 'toggleAnnouncement':

            checkAuth();

            toggleAnnouncement();

            break;



        case 'deleteAnnouncement':

            checkAuth();

            deleteAnnouncement();

            break;



        case 'test':

            sendJSON([

                'success' => true,

                'message' => 'API is working!',

                'timestamp' => date('Y-m-d H:i:s')

            ]);

            break;



        case 'getStudentByPhone':

            getStudentByPhone();

            break;



        case 'getStudentAttendance':

            getStudentAttendance();

            break;



        case 'getAnnouncementsForStudent':

            getAnnouncementsForStudent();

            break;



        case 'getAllChurches':

            getAllChurches();

            break;



        case 'submitRegistrationRequest':

            submitRegistrationRequest();

            break;



        case 'getPublicStats':

            getPublicStats();

            break;



        case 'approveRegistration':

            checkAuth();

            approveRegistration();

            break;



        case 'getPendingRegistrations':

            checkAuth();

            getPendingRegistrations();

            break;



        case 'updateRegistration':

            checkAuth();

            updateRegistration();

            break;



        case 'bulkUpdateRegistrations':

            checkAuth();

            bulkUpdateRegistrations();

            break;



        case 'rejectRegistration':

            checkAuth();

            rejectRegistration();

            break;

        case 'getAllChurches':

            getAllChurches();

            break;

        case 'addChurch':

            checkAuth();

            addChurch();

            break;



        case 'getAllChurchesForAdmin':

            checkAuth();

            getAllChurchesForAdmin();

            break;



        case 'updateChurch':

            checkAuth();

            updateChurch();

            break;



        case 'updateChurchPassword':

            checkAuth();

            updateChurchPassword();

            break;



        case 'deleteChurch':

            checkAuth();

            deleteChurch();

            break;



        case 'cleanupOrphanedFiles':

            cleanupOrphanedFiles();

            break;



        case 'generateKidsTemplate':

            generateKidsTemplate();

            break;



        case 'exportKidsData':

            checkAuth();

            exportKidsData();

            break;



        case 'bulkAddKids':

            checkAuth();

            bulkAddKids();

            break;



        case 'getKidsData':

            checkAuth();

            getKidsData();

            break;

        case 'kidLogin':

            kidLogin();

            break;



        case 'checkUsernameAvailable':

            checkUsernameAvailable();

            break;



        case 'checkKidPasswordByPhone':

            checkKidPasswordByPhone();

            break;



        case 'kidLoginByPhoneWithPassword':

            kidLoginByPhoneWithPassword();

            break;

        case 'setupStudentPassword':

            setupStudentPassword();

            break;

        case 'getStudentProfile':

            getStudentProfile();

            break;

        case 'getSiblingGroupMembers':
            getSiblingGroupMembers();
            break;

        case 'shareCoupons':
            shareCoupons();
            break;

        case 'changeStudentPassword':
            changeStudentPassword();
            break;


        case 'updateStudentInfo':

            updateStudentInfo();

            break;

        case 'searchKidsByName':

            searchKidsByName();

            break;

        case 'updateStudentAttendance':

            updateStudentAttendance();

            break;



        case 'updateCouponsKids':

            updateCouponsKids();

            break;

        case 'updateStudentImageAfterCreation':

            checkAuth();

            updateStudentImageAfterCreation();

            break;

        case 'hasTempAttendance':

            hasTempAttendance();

            break;

        case 'getChurchStatistics':

            checkAuth();

            getChurchStatistics();

            break;

        case 'getClassDetails':

            checkAuth();

            getClassDetails();

            break;

        case 'getStudentAttendanceDetails':

            checkAuth();

            getStudentAttendanceDetails();

            break;

        case 'updateCouponsWithReason':

            checkAuth();

            updateCouponsWithReason();

            break;

        case 'getCouponLogs':

            checkAuth();

            getCouponLogs();

            break;

        case 'getChurchClasses':

            getChurchClasses();

            break;



        case 'saveChurchClasses':

            checkAuth();

            saveChurchClasses();

            break;



        case 'addChurchClass':

            checkAuth();

            addChurchClass();

            break;



        case 'updateChurchClass':

            checkAuth();

            updateChurchClass();

            break;



        case 'deleteChurchClass':

            checkAuth();

            deleteChurchClass();

            break;



        case 'resetChurchClasses':

            checkAuth();

            resetChurchClasses();

            break;



        case 'reorderChurchClasses':

            checkAuth();

            reorderChurchClasses();

            break;



        case 'getChurchClassesForAdmin':

            getChurchClassesForAdmin();

            break;

        case 'getPublicChurchClasses':

            getPublicChurchClasses();

            break;

        case 'getChurchClassesWithStats':

            checkAuth(); // يتطلب تسجيل دخول

            getChurchClassesWithStats();

            break;

        // دوال الرحلات / المؤتمرات

        case 'getTrips':

            getTrips();

            break;

        case 'getGuests':

            getGuests();

            break;

        case 'addGuest':

            addGuest();

            break;

        case 'updateGuest':

            updateGuest();

            break;

        case 'deleteGuest':

            deleteGuest();

            break;

        case 'transferGuestToStudent':

            transferGuestToStudent();

            break;

        case 'addTrip':

            addTrip();

            break;

        case 'updateTrip':

            updateTrip();

            break;

        case 'deleteTrip':

            deleteTrip();

            break;

        case 'getCustomFieldTemplates':

            checkAuth();

            getCustomFieldTemplates();

            break;

        case 'addCustomFieldTemplate':

            checkAuth();

            addCustomFieldTemplate();

            break;

        case 'deleteCustomFieldTemplate':

            checkAuth();

            deleteCustomFieldTemplate();

            break;

        case 'getTripDetails':

            getTripDetails();

            break;

        case 'registerStudentForTrip':

            registerStudentForTrip();

            break;

        case 'addTripPayment':

            addTripPayment();

            break;

        case 'cancelTripRegistration':

            cancelTripRegistration();

            break;

        case 'exportTripData':

            exportTripData();

            break;

        case 'deleteTripPayment':

            deleteTripPayment();

            break;

        case 'restoreTripPayment':

            restoreTripPayment();

            break;

        case 'sendCollaborationRequest':

            checkAuth();

            sendCollaborationRequest();

            break;

        case 'getCollaborationRequests':

            checkAuth();

            getCollaborationRequests();

            break;

        case 'respondToCollaborationRequest':

            checkAuth();

            respondToCollaborationRequest();

            break;

        case 'removeTripCollaborator':

            checkAuth();

            removeTripCollaborator();

            break;





        // دوال تحسين الحضور

        case 'getFridaysInMonth':

            getFridaysInMonth();

            break;

        case 'getAttendanceByDateAndClass':

            getAttendanceByDateAndClass();

            break;

        case 'updateSingleAttendance':

            updateSingleAttendance();

            break;

        case 'deleteAttendance':

            deleteAttendance();

            break;



        // دوال إعدادات الفصول

        case 'getClassSettings':

            getClassSettings();

            break;

        case 'saveClassSettings':

            saveClassSettings();

            break;



        // دوال تحديث الأطفال

        case 'updateStudentFull':

            updateStudentFull();

            break;

        case 'getTripExpenses':

            getTripExpenses();

            break;

        case 'saveTripExpense':

            saveTripExpense();

            break;

        case 'deleteTripExpense':

            deleteTripExpense();

            break;

        case 'getAttendanceByDate':

            getAttendanceByDate();

            break;



        // ── Uncle Attendance ─────────────────────────────────────────────

        case 'submitUncleAttendance':

            checkUncleAuth();

            submitUncleAttendance();

            break;



        case 'getUncleAttendanceByDate':

            checkUncleAuth();

            getUncleAttendanceByDate();

            break;



        case 'getUncleAttendanceReport':

            checkUncleAuth();

            getUncleAttendanceReport();

            break;



        case 'toggleUncleAttendance':

            checkUncleAuth();

            toggleUncleAttendance();

            break;



        case 'deleteUncleAttendance':

            checkUncleAuth();

            deleteUncleAttendance();

            break;

        case 'getSessionInfo':

            getSessionInfo();

            break;

        case 'getClassUncles':

            checkAuth();

            getClassUncles();

            break;



        case 'assignUncleToClass':

            checkAuth();

            assignUncleToClass();

            break;



        case 'removeUncleFromClass':

            checkAuth();

            removeUncleFromClass();

            break;



        // ── NEW: Attendance day + Church settings ──────────────────

        case 'getChurchSettings':

            getChurchSettings();

            break;



        case 'saveChurchSettings':

            checkAuth();

            saveChurchSettings();

            break;



        case 'gradeUpAllKids':

            gradeUpAllKids();

            break;



        case 'getGraduates':

            checkAuth();

            getGraduates();

            break;



        case 'deleteGraduateStudent':

            deleteGraduateStudent();

            break;



        case 'exportGraduateStudent':

            checkAuth();

            exportGraduateStudent();

            break;



        case 'sendGraduateToChurch':

            checkAuth();

            sendGraduateToChurch();

            break;



        case 'respondGraduateTransfer':

            checkAuth();

            respondGraduateTransfer();

            break;



        case 'getChurchesForTransfer':

            checkAuth();

            getChurchesForTransfer();

            break;



        case 'restoreGraduateToClass':

            checkAuth();

            restoreGraduateToClass();

            break;



        case 'getUncleClassView':

            checkUncleAuth();

            getUncleClassView();

            break;

        case 'getClassUncles':

            checkAuth();

            getClassUncles();

            break;

        case 'assignUncleToClass':

            checkAuth();

            assignUncleToClass();

            break;

        case 'removeUncleFromClass':

            checkAuth();

            removeUncleFromClass();

            break;

        case 'testAudit':

            $conn = getDBConnection();



            // Test 1: Can we query the table?

            $check = $conn->query("SELECT COUNT(*) as cnt FROM audit_logs");

            $count = $check ? $check->fetch_assoc()['cnt'] : 'QUERY FAILED: ' . $conn->error;



            // Test 2: Can we insert directly?

            $insert = $conn->query("

        INSERT INTO audit_logs 

        (church_id, uncle_id, uncle_name, action, entity, entity_id, entity_name, notes)

        VALUES (1, 18, 'Test', 'test_action', 'test', 1, 'direct test', 'raw query test')

    ");

            $insertResult = $insert ? 'INSERT OK — id: ' . $conn->insert_id : 'INSERT FAILED: ' . $conn->error;



            // Test 3: Can we insert via prepared statement?

            $stmt = $conn->prepare("

        INSERT INTO audit_logs 

        (church_id, uncle_id, uncle_name, action, entity, notes)

        VALUES (?, ?, ?, ?, ?, ?)

    ");

            $cid = 1;

            $uid = 18;

            $uname = 'Test';

            $act = 'test_prepared';

            $ent = 'test';

            $n = 'prepared stmt test';

            $stmt->bind_param("iissss", $cid, $uid, $uname, $act, $ent, $n);

            $prepResult = $stmt->execute() ? 'PREPARED OK — id: ' . $conn->insert_id : 'PREPARED FAILED: ' . $stmt->error;



            // Test 4: Call writeAuditLog directly

            writeAuditLog('test_direct', 'test', 999, 'direct call test', null, null, 'writeAuditLog direct test');

            $afterDirect = $conn->query("SELECT COUNT(*) as cnt FROM audit_logs")->fetch_assoc()['cnt'];



            echo json_encode([

                'table_count_before' => $count,

                'raw_insert' => $insertResult,

                'prepared_insert' => $prepResult,

                'count_after_direct' => $afterDirect,

                'session_church_id' => $_SESSION['church_id'] ?? 'NOT SET',

                'session_uncle_id' => $_SESSION['uncle_id'] ?? 'NOT SET',

                'db_error' => $conn->error,

            ]);

            exit;

            break;

        case 'debugAudit':

            $conn = getDBConnection();

            $result = $conn->query("SELECT id, church_id, uncle_id, uncle_name, action, entity, created_at FROM audit_logs ORDER BY id DESC LIMIT 20");

            $rows = [];

            while ($r = $result->fetch_assoc())

                $rows[] = $r;

            echo json_encode([

                'rows' => $rows,

                'session_church_id' => $_SESSION['church_id'] ?? 'none',

                'get_church_id_returns' => getChurchId()

            ]);

            exit;

            break;

        case 'restore_session':

            // NOTE: session_start() already called at top of file — do NOT call again

            // Do NOT wipe $_SESSION before restoring — just overwrite the keys we need



            if (isset($_POST['church_code'])) {

                $church_code = sanitize($_POST['church_code']);



                $row = null;

                try {

                    $conn = getDBConnection();

                    ensureChurchTypeColumn($conn);

                    $stmt = $conn->prepare("SELECT id, church_name, church_code, COALESCE(church_type,'kids') AS church_type FROM churches WHERE church_code = ?");

                    $stmt->bind_param("s", $church_code);

                    $stmt->execute();

                    $row = $stmt->get_result()->fetch_assoc();

                } catch (Exception $e) {

                    error_log("restore_session church error: " . $e->getMessage());

                }



                if ($row) {

                    $_SESSION['church_id'] = $row['id'];

                    $_SESSION['church_name'] = $row['church_name'];

                    $_SESSION['church_code'] = $row['church_code'];

                    $_SESSION['church_type'] = $row['church_type'];

                    $_SESSION['permanent'] = true;



                    error_log("Session restored for church: " . $row['church_name']);

                    echo json_encode([

                        'success' => true,

                        'church_type' => $row['church_type'],

                        'church_name' => $row['church_name'],

                    ]);

                } else {

                    error_log("Church not found with code: " . $church_code);

                    echo json_encode(['success' => false, 'message' => 'Church not found']);

                }

            } elseif (isset($_POST['username'])) {

                $username = sanitize($_POST['username']);



                $row = null;

                try {

                    $conn = getDBConnection();

                    ensureChurchTypeColumn($conn);

                    $stmt = $conn->prepare("

                SELECT u.id, u.name, u.username, u.role, u.church_id,

                       c.church_name, c.church_code,

                       COALESCE(c.church_type, 'kids') AS church_type

                FROM uncles u

                LEFT JOIN churches c ON u.church_id = c.id

                WHERE u.username = ? AND (u.deleted IS NULL OR u.deleted = 0)

            ");

                    $stmt->bind_param("s", $username);

                    $stmt->execute();

                    $row = $stmt->get_result()->fetch_assoc();

                } catch (Exception $e) {

                    error_log("restore_session uncle error: " . $e->getMessage());

                }



                if ($row) {

                    $_SESSION['uncle_id'] = $row['id'];

                    $_SESSION['uncle_name'] = $row['name'];

                    $_SESSION['uncle_username'] = $row['username'];

                    $_SESSION['uncle_role'] = $row['role'];

                    $_SESSION['church_id'] = $row['church_id'];

                    $_SESSION['church_name'] = $row['church_name'];

                    $_SESSION['church_code'] = $row['church_code'];

                    $_SESSION['church_type'] = $row['church_type'];

                    $_SESSION['permanent'] = true;



                    error_log("Session restored for uncle: " . $row['username']);

                    echo json_encode([

                        'success' => true,

                        'church_type' => $row['church_type'],

                        'church_name' => $row['church_name'],

                        'uncle_name' => $row['name'],

                        'uncle_role' => $row['role'],

                        'uncle' => ['role' => $row['role']],

                    ]);

                } else {

                    error_log("Uncle not found with username: " . $username);

                    echo json_encode(['success' => false, 'message' => 'User not found']);

                }

            } else {

                echo json_encode(['success' => false, 'message' => 'No credentials provided']);

            }

            break;



        // ── Public church settings (no auth) ────────────────────────

        case 'getPublicChurchSettings':

            getPublicChurchSettings();

            break;



        case 'getPublicClassUncles':

            getPublicClassUncles();

            break;



        case 'debugKidProfile':

            debugKidProfile();

            break;



        // ── Tasks ────────────────────────────────────────────────────

        case 'getTasks':

            checkUncleAuth();

            getTasks();

            break;



        case 'getTaskDetail':

            checkUncleAuth();

            getTaskDetail();

            break;



        case 'createTask':

            checkUncleAuth();

            createTask();

            break;



        case 'updateTask':

            checkUncleAuth();

            updateTask();

            break;



        case 'deleteTask':

            checkUncleAuth();

            deleteTask();

            break;



        case 'deleteSubmission':

            checkUncleAuth();

            deleteSubmission();

            break;



        case 'getStudentTasks':

            getStudentTasks();

            break;



        case 'submitTaskAnswers':

            submitTaskAnswers();

            break;



        case 'deleteSubmission':

            checkUncleAuth();

            deleteSubmission();

            break;



        case 'startExam':

            startExam();

            break;



        case 'getExamStart':

            getExamStart();

            break;



        case 'clearExamStart':

            clearExamStart();

            break;



        case 'fetchOgImage':

            fetchOgImage();

            break;



        case 'getStudentTrips':

            getStudentTrips();

            break;



        // ── Push Subscriptions ───────────────────────────────────

        case 'savePushSubscription':

            savePushSubscription();

            break;



        case 'sendPushNotification':

            sendPushNotificationAction();

            break;



        // ── Notifications ────────────────────────────────────────

        case 'getNotifications':

            checkAuth();

            getNotifications();

            break;



        case 'markNotificationRead':

            checkAuth();

            markNotificationRead();

            break;



        case 'deleteNotification':

            checkAuth();

            deleteNotification();

            break;



        case 'markAllNotificationsRead':

            checkAuth();

            markAllNotificationsRead();

            break;



        // ── Developer Messages ───────────────────────────────────

        case 'sendDeveloperMessage':

            sendDeveloperMessage();

            break;



        case 'getDeveloperMessages':

            getDeveloperMessages();

            break;



        case 'markDevMessageRead':

            markDevMessageRead();

            break;



        case 'deleteDevMessage':

            deleteDevMessage();

            break;



        // ── Open Question Grading ────────────────────────────────

        case 'getPendingOpenSubmissions':

            checkUncleAuth();

            getPendingOpenSubmissions();

            break;



        case 'gradeOpenAnswer':

            checkUncleAuth();

            gradeOpenAnswer();

            break;



        case 'processGameQRCode':

            processGameQRCode();

            break;



        case 'getTrips':

            getTrips();

            break;



        case 'getTripDetails':

            getTripDetails();

            break;



        case 'addTrip':

            checkAuth();

            addTrip();

            break;



        case 'updateTrip':

            checkAuth();

            updateTrip();

            break;



        case 'deleteTrip':

            checkAuth();

            deleteTrip();

            break;



        case 'sendCollaborationRequest':

            checkAuth();

            sendCollaborationRequest();

            break;



        case 'getCollaborationRequests':

            checkAuth();

            getCollaborationRequests();

            break;



        case 'respondToCollaborationRequest':

            checkAuth();

            respondToCollaborationRequest();

            break;

        case 'removeTripCollaborator':

            checkAuth();

            removeTripCollaborator();

            break;





        case 'registerStudentForTrip':

            registerStudentForTrip();

            break;



        case 'addTripPayment':

            addTripPayment();

            break;



        case 'cancelTripRegistration':

            cancelTripRegistration();

            break;



        case 'exportTripData':

            exportTripData();

            break;



        case 'bulkUpdateCustomData':

            bulkUpdateCustomData();

            break;



        case 'getWaitlist':

            getWaitlistAction();

            break;



        case 'removeFromWaitlist':

            removeFromWaitlist();

            break;



        case 'rebalanceTripWaitlist':

            rebalanceTripWaitlist();

            break;



        case 'addTripWaitlistPayment':

            addTripWaitlistPayment();

            break;



        case 'deleteTripWaitlistPayment':

            deleteTripWaitlistPayment();

            break;



        case 'restoreTripWaitlistPayment':

            restoreTripWaitlistPayment();

            break;





        case 'withdrawCoupons':

            checkUncleAuth();

            withdrawCoupons();

            break;



        case 'getWithdrawalHistory':

            checkUncleAuth();

            getWithdrawalHistory();

            break;



        case 'refundWithdrawal':

            checkUncleAuth();

            refundWithdrawal();

            break;



        case 'getSessionInfo':

            getSessionInfo();

            break;



        default:

            // Fall through — second switch below handles remaining actions

            break;

    }

} catch (Exception $e) {

    error_log("API Error (switch 1): " . $e->getMessage());

    sendJSON(['success' => false, 'message' => 'خطأ في السيرفر: ' . $e->getMessage()]);

}



// ===== LOGIN FUNCTION =====

function handleLogin()

{

    try {

        $churchCode = sanitize($_POST['church_code'] ?? '');

        $password = $_POST['password'] ?? '';



        if (empty($churchCode) || empty($password)) {

            sendJSON(['success' => false, 'message' => 'الرجاء إدخال رمز الكنيسة وكلمة المرور']);

        }



        $passwordHash = hash('sha256', $password);



        try {

            $conn = getDBConnection();

            ensureChurchTypeColumn($conn);

            $stmt = $conn->prepare("SELECT id, church_name, password_hash, COALESCE(church_type,'kids') AS church_type FROM churches WHERE church_code = ?");

            $stmt->bind_param("s", $churchCode);

            $stmt->execute();

            $row = $stmt->get_result()->fetch_assoc();



            if ($row && $passwordHash === $row['password_hash']) {

                $_SESSION['church_id'] = $row['id'];

                $_SESSION['church_name'] = $row['church_name'];

                $_SESSION['church_code'] = $churchCode;

                $_SESSION['church_type'] = $row['church_type'];

                $_SESSION['login_type'] = 'church';



                auditLogin('church', $row['id'], $row['church_name']);

                runBackgroundGradeUpChecks();



                sendJSON([

                    'success' => true,

                    'message' => 'تم تسجيل الدخول بنجاح',

                    'church_name' => $row['church_name'],

                    'church_id' => $row['id'],

                    'church_type' => $row['church_type'],

                ]);

            }

        } catch (Exception $e) {

            error_log("handleLogin DB error: " . $e->getMessage());

        }



        sendJSON(['success' => false, 'message' => 'رمز الكنيسة أو كلمة المرور غير صحيحة']);



    } catch (Exception $e) {

        error_log("Login error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تسجيل الدخول']);

    }

}



// ===== GET DATA FUNCTION =====

// Helper function to get all classes

function getAllClasses()

{

    $conn = getDBConnection();

    $stmt = $conn->prepare("

        SELECT id, code, arabic_name, display_order 

        FROM classes 

        ORDER BY display_order

    ");

    $stmt->execute();

    $result = $stmt->get_result();



    $classes = [];

    while ($row = $result->fetch_assoc()) {

        $classes[] = $row;

    }

    return $classes;

}



// Helper function to get class by ID

function getClassById($classId)

{

    $conn = getDBConnection();

    $stmt = $conn->prepare("

        SELECT code, arabic_name 

        FROM classes 

        WHERE id = ?

    ");

    $stmt->bind_param("i", $classId);

    $stmt->execute();

    $result = $stmt->get_result();

    return $result->fetch_assoc();

}



// Helper function to get class by code

function getClassByCode($classCode)

{

    $conn = getDBConnection();

    $stmt = $conn->prepare("

        SELECT id, arabic_name 

        FROM classes 

        WHERE code = ?

    ");

    $stmt->bind_param("s", $classCode);

    $stmt->execute();

    $result = $stmt->get_result();

    return $result->fetch_assoc();

}

function getAllClassesForDropdown_churchAware(): array

{

    try {

        $churchId = getChurchId();

        if ($churchId > 0) {

            return getClassesForChurch($churchId);

        }

        // No church context — fall back to global

        $conn = getDBConnection();

        $stmt = $conn->prepare("SELECT id, code, arabic_name, display_order FROM classes ORDER BY display_order");

        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    } catch (Exception $e) {

        error_log("getAllClassesForDropdown_churchAware error: " . $e->getMessage());

        return [];

    }

}



function getData()

{

    try {

        $conn = getDBConnection();

        ensureStudentSiblingGroupTables($conn);

        $churchId = getChurchId();

        $isAll = (!empty($_POST['all_churches']) && $_POST['all_churches'] === '1');

        if ($isAll) {

            $stmt = $conn->prepare("

        SELECT 

            s.id, s.name, s.phone, s.birthday, s.coupons,

            s.attendance_coupons, s.commitment_coupons, s.task_coupons,

            s.emergency_phone, s.medical_notes, s.custom_info,

            s.image_url, s.class_id, s.gender,

            COALESCE(cc.arabic_name, gc.arabic_name, s.class) as class,

            COALESCE(cc.code, gc.code) as class_code,

            c.church_name, c.id as church_id_val

        FROM students s

        LEFT JOIN church_classes cc ON s.class_id = cc.id AND cc.church_id = s.church_id

        LEFT JOIN classes gc ON s.class_id = gc.id

        LEFT JOIN churches c ON s.church_id = c.id

        WHERE COALESCE(s.enrollment_status, 'active') = 'active'

        ORDER BY c.church_name, s.name

    ");

            $stmt->execute();

            $result = $stmt->get_result();

            $students = [];

            while ($row = $result->fetch_assoc()) {

                $studentData = [

                    'الاسم' => $row['name'],

                    '_churchName' => $row['church_name'] ?? '',

                    'الفصل' => $row['class'] ?? 'بدون فصل',

                    '_classCode' => $row['class_code'] ?? '',

                    '_classId' => intval($row['class_id']),

                    'العنوان' => '',

                    'رقم التليفون' => $row['phone'] ?? '',

                    'عيد الميلاد' => formatDateFromDB($row['birthday']),

                    'كوبونات' => intval($row['coupons']),

                    'كوبونات الحضور' => intval($row['attendance_coupons']),

                    'كوبونات الالتزام' => intval($row['commitment_coupons']),

                    'كوبونات المهام' => intval($row['task_coupons']),

                    'تليفون الطوارئ' => $row['emergency_phone'] ?? '',

                    'ملاحظات طبية' => $row['medical_notes'] ?? '',

                    'معلومات إضافية' => $row['custom_info'] ?? '',

                    'صورة' => $row['image_url'] ?? '',

                    '_studentId' => intval($row['id']),

                    'النوع' => $row['gender'] ?? 'male',

                    '_allAttendance' => [],

                ];

                $students[] = $studentData;

            }

            // Get distinct classes across all churches for the filter dropdown

            $classRows = $conn->query("

        SELECT DISTINCT COALESCE(cc.arabic_name, gc.arabic_name) as arabic_name,

               COALESCE(cc.id, gc.id) as id, COALESCE(cc.code, gc.code) as code,

               COALESCE(cc.display_order, gc.display_order) as display_order

        FROM students s

        LEFT JOIN church_classes cc ON s.class_id = cc.id AND cc.church_id = s.church_id

        LEFT JOIN classes gc ON s.class_id = gc.id

        ORDER BY display_order

    ");

            $allClasses = [];

            while ($r = $classRows->fetch_assoc()) {

                $allClasses[] = $r;

            }

            sendJSON(['success' => true, 'data' => $students, 'allStudents' => $students, 'classes' => $allClasses]);

            return;

        }



        // Debug church ID

        error_log("=== getData() called ===");

        error_log("Church ID from getChurchId(): " . $churchId);



        if ($churchId === 0) {

            error_log("ERROR: Church ID is 0 - cannot fetch data");

            sendJSON([

                'success' => false,

                'message' => 'معرف الكنيسة غير صالح',

                'debug' => 'Church ID is 0'

            ]);

            return;

        }



        $conn = getDBConnection();



        // First, check if church exists

        $checkChurch = $conn->prepare("SELECT id, church_name FROM churches WHERE id = ?");

        $checkChurch->bind_param("i", $churchId);

        $checkChurch->execute();

        $churchResult = $checkChurch->get_result();



        if ($churchResult->num_rows === 0) {

            error_log("ERROR: Church with ID " . $churchId . " not found in database");

            sendJSON([

                'success' => false,

                'message' => 'الكنيسة غير موجودة في قاعدة البيانات'

            ]);

            return;

        }



        $churchData = $churchResult->fetch_assoc();

        error_log("Church found: " . $churchData['church_name'] . " (ID: " . $churchData['id'] . ")");



        ensureStudentGraduateSchema($conn);



        $tripId = intval($_POST['trip_id'] ?? 0);

        $allowedChurches = [$churchId];

        if ($tripId > 0) {

            $tStmt = $conn->prepare("SELECT church_id, collaborating_churches FROM trips WHERE id = ?");

            $tStmt->bind_param("i", $tripId);

            $tStmt->execute();

            $trip = $tStmt->get_result()->fetch_assoc();

            if ($trip) {

                $participants = [intval($trip['church_id'])];

                $collabRaw = $trip['collaborating_churches'] ?? '';

                if (!empty($collabRaw)) {

                    $collab = json_decode($collabRaw, true);

                    if (is_array($collab)) {

                        $participants = array_unique(array_merge($participants, array_map('intval', $collab)));

                    }

                }

                if (in_array($churchId, $participants)) {

                    $allowedChurches = $participants;

                }

            }

        }



        $inPlaceholder = implode(',', array_map('intval', $allowedChurches));



        $stmt = $conn->prepare("

            SELECT 

                s.id, 

                s.name, 

                s.address, 

                s.phone, 

                s.birthday, 

                s.coupons, 

                s.attendance_coupons, 

                s.commitment_coupons, 

                s.task_coupons,

                s.emergency_phone,

                s.medical_notes,

                s.image_url,

                s.class_id,

                s.custom_info,

                s.church_id,

                s.gender,

                c.church_name,

                COALESCE(cc.id, gc.id) as class_id,

                COALESCE(cc.code, gc.code) as class_code, 

                COALESCE(cc.arabic_name, gc.arabic_name) as class,

                ssgm.group_id AS sibling_group_id,

                ssg.label AS sibling_group_label,

                ssg.status AS sibling_group_status,

                ssg.linked_by_id AS sibling_group_linked_by_id,

                ssg.linked_by_name AS sibling_group_linked_by_name,

                ssg.updated_at AS sibling_group_updated_at,

                ssgm.added_at AS sibling_member_added_at

            FROM students s

            LEFT JOIN church_classes cc 

                ON cc.id = s.class_id AND cc.church_id = s.church_id AND cc.is_active = 1

            LEFT JOIN classes gc 

                ON gc.id = s.class_id

            LEFT JOIN churches c

                ON s.church_id = c.id

            LEFT JOIN student_sibling_group_members ssgm ON ssgm.student_id = s.id

            LEFT JOIN student_sibling_groups ssg ON ssg.id = ssgm.group_id

            WHERE s.church_id IN ($inPlaceholder)

              AND COALESCE(s.enrollment_status, 'active') = 'active'

            ORDER BY 

                c.church_name,

                CASE 

                    WHEN COALESCE(cc.arabic_name, gc.arabic_name) IS NULL THEN 999 

                    ELSE COALESCE(cc.display_order, gc.display_order) 

                END, 

                s.name

        ");



        if (!$stmt) {

            error_log("SQL Prepare error: " . $conn->error);

            sendJSON(['success' => false, 'message' => 'خطأ في تحضير الاستعلام']);

            return;

        }



        if (!$stmt->execute()) {

            error_log("SQL Execute error: " . $stmt->error);

            sendJSON(['success' => false, 'message' => 'خطأ في تنفيذ الاستعلام']);

            return;

        }



        $result = $stmt->get_result();



        if (!$result) {

            error_log("Result error: " . $conn->error);

            sendJSON(['success' => false, 'message' => 'خطأ في الحصول على النتائج']);

            return;

        }



        $students = [];

        $studentsWithNullClass = 0;



        error_log("Processing student records...");



        while ($row = $result->fetch_assoc()) {

            // Debug each student

            error_log("Student found - ID: " . $row['id'] . ", Name: " . $row['name'] . ", Class ID: " . ($row['class_id'] ?? 'NULL') . ", Class: " . ($row['class'] ?? 'NULL'));



            // Handle case where class is NULL

            if (empty($row['class'])) {

                $studentsWithNullClass++;

                $row['class'] = 'بدون فصل';

                $row['class_code'] = 'no_class';

                $row['class_id'] = 0;

            }



            $studentData = [

                'الاسم' => $row['name'],

                'الفصل' => $row['class'],

                '_classId' => intval($row['class_id']),

                '_classCode' => $row['class_code'] ?? '',

                '_churchId' => intval($row['church_id']),

                '_churchName' => $row['church_name'] ?? '',

                'العنوان' => $row['address'] ?? '',

                'رقم التليفون' => $row['phone'] ?? '',

                'عيد الميلاد' => formatDateFromDB($row['birthday']),

                'كوبونات' => intval($row['coupons']),

                'كوبونات الحضور' => intval($row['attendance_coupons']),

                'كوبونات الالتزام' => intval($row['commitment_coupons']),

                'كوبونات المهام' => intval($row['task_coupons']),

                'تليفون الطوارئ' => $row['emergency_phone'] ?? '',

                'ملاحظات طبية' => $row['medical_notes'] ?? '',

                'معلومات إضافية' => $row['custom_info'] ?? '',

                'صورة' => $row['image_url'] ?? '',

                '_studentId' => intval($row['id']),

                'النوع' => $row['gender'] ?? 'male',

                '_customInfo' => !empty($row['custom_info'])

                    ? json_decode($row['custom_info'], true)

                    : null,

            ];

            appendSiblingGroupToStudentPayload($studentData, $row);



            // Get attendance records for this student

            $attendanceStmt = $conn->prepare("

                SELECT attendance_date, status 

                FROM attendance 

                WHERE student_id = ? 

                ORDER BY attendance_date DESC

                LIMIT 50

            ");



            if ($attendanceStmt) {

                $attendanceStmt->bind_param("i", $row['id']);

                $attendanceStmt->execute();

                $attendanceResult = $attendanceStmt->get_result();



                $studentData['_allAttendance'] = [];



                while ($attRow = $attendanceResult->fetch_assoc()) {

                    $date = formatDateFromDB($attRow['attendance_date']);

                    $status = $attRow['status'] === 'present' ? 'ح' : 'غ';

                    $studentData[$date] = $status;

                    $studentData['_allAttendance'][$date] = $status;

                }

            }



            $students[] = $studentData;

        }



        error_log("Total students processed: " . count($students));

        error_log("Students with NULL class: " . $studentsWithNullClass);



        // Get all classes for dropdowns

        $classes = getAllClassesForDropdown_churchAware();

        error_log("Classes loaded: " . count($classes));



        // Double-check with a simple count query

        $simpleCount = $conn->query("SELECT COUNT(*) as cnt FROM students WHERE church_id = $churchId");

        $simpleCountResult = $simpleCount->fetch_assoc();

        error_log("Simple count verification: " . $simpleCountResult['cnt'] . " students");



        // If we have students in DB but none in our result, there's a problem

        if ($simpleCountResult['cnt'] > 0 && count($students) === 0) {

            error_log("WARNING: Database has " . $simpleCountResult['cnt'] . " students but query returned none!");



            // Try a simpler query without JOIN

            $fallbackStmt = $conn->prepare("

                SELECT id, name, address, phone, birthday, 

                       coupons, attendance_coupons, commitment_coupons, image_url,

                       class_id

                FROM students 

                WHERE church_id = ?

                ORDER BY name

            ");

            $fallbackStmt->bind_param("i", $churchId);

            $fallbackStmt->execute();

            $fallbackResult = $fallbackStmt->get_result();



            $fallbackStudents = [];

            while ($row = $fallbackResult->fetch_assoc()) {

                $fallbackStudents[] = [

                    'الاسم' => $row['name'],

                    'الفصل' => 'فصل غير معروف',

                    '_classId' => intval($row['class_id']),

                    'العنوان' => $row['address'] ?? '',

                    'رقم التليفون' => $row['phone'] ?? '',

                    'عيد الميلاد' => formatDateFromDB($row['birthday']),

                    'كوبونات' => intval($row['coupons']),

                    'كوبونات الحضور' => intval($row['attendance_coupons']),

                    'كوبونات الالتزام' => intval($row['commitment_coupons']),

                    'صورة' => $row['image_url'] ?? '',

                    '_studentId' => intval($row['id'])

                ];

            }



            error_log("Fallback query found: " . count($fallbackStudents) . " students");



            sendJSON([

                'success' => true,

                'data' => $fallbackStudents,

                'allStudents' => $fallbackStudents,

                'classes' => $classes,

                'debug' => [

                    'db_count' => $simpleCountResult['cnt'],

                    'returned_count' => count($fallbackStudents),

                    'using_fallback' => true

                ]

            ]);

            return;

        }



        sendJSON([

            'success' => true,

            'data' => $students,

            'allStudents' => $students,

            'classes' => $classes,

            'debug' => [

                'church_id' => $churchId,

                'total_in_db' => $simpleCountResult['cnt'],

                'returned_count' => count($students),

                'students_with_null_class' => $studentsWithNullClass

            ]

        ]);



    } catch (Exception $e) {

        error_log("=== EXCEPTION in getData() ===");

        error_log("Error message: " . $e->getMessage());

        error_log("Stack trace: " . $e->getTraceAsString());



        sendJSON([

            'success' => false,

            'message' => 'خطأ في جلب البيانات: ' . $e->getMessage(),

            'debug' => [

                'error' => $e->getMessage(),

                'file' => $e->getFile(),

                'line' => $e->getLine()

            ]

        ]);

    }

}



function withdrawCoupons()

{

    try {
        $conn = getDBConnection();
        $conn->query("CREATE TABLE IF NOT EXISTS coupon_withdrawals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            uncle_id INT NOT NULL,
            amount INT NOT NULL,
            att_amount INT DEFAULT 0,
            com_amount INT DEFAULT 0,
            tsk_amount INT DEFAULT 0,
            note TEXT,
            is_refunded TINYINT(1) DEFAULT 0,
            refunded_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX(student_id),
            INDEX(created_at)
        )");
        $conn->query("ALTER TABLE coupon_withdrawals ADD COLUMN IF NOT EXISTS att_amount INT DEFAULT 0 AFTER amount");
        $conn->query("ALTER TABLE coupon_withdrawals ADD COLUMN IF NOT EXISTS com_amount INT DEFAULT 0 AFTER att_amount");
        $conn->query("ALTER TABLE coupon_withdrawals ADD COLUMN IF NOT EXISTS tsk_amount INT DEFAULT 0 AFTER com_amount");

        $studentId = intval($_POST['student_id'] ?? 0);
        $amount = intval($_POST['amount'] ?? 0);
        $note = sanitize($_POST['note'] ?? '');
        $uncleId = $_SESSION['uncle_id'] ?? 0;

        if ($studentId <= 0 || $amount <= 0) {
            sendJSON(['success' => false, 'message' => 'بيانات غير صحيحة']);
            return;
        }

        $category = sanitize($_POST['category'] ?? 'all');

        $conn->begin_transaction();

        // Get current coupons with breakdown
        $stmt = $conn->prepare("SELECT coupons, attendance_coupons, commitment_coupons, task_coupons FROM students WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        if (!$res) {
            throw new Exception('الطفل غير موجود');
        }

        $current = intval($res['coupons']);
        $att_avail = intval($res['attendance_coupons']);
        $com_avail = intval($res['commitment_coupons']);
        $tsk_avail = intval($res['task_coupons']);

        if ($category === 'att') {
            if ($att_avail < $amount) {
                throw new Exception('رصيد كوبونات الحضور غير كافٍ');
            }
            $att_sub = $amount;
            $com_sub = 0;
            $tsk_sub = 0;
        } elseif ($category === 'com') {
            if ($com_avail < $amount) {
                throw new Exception('رصيد كوبونات الالتزام غير كافٍ');
            }
            $att_sub = 0;
            $com_sub = $amount;
            $tsk_sub = 0;
        } elseif ($category === 'task') {
            if ($tsk_avail < $amount) {
                throw new Exception('رصيد كوبونات المهام غير كافٍ');
            }
            $att_sub = 0;
            $com_sub = 0;
            $tsk_sub = $amount;
        } else {
            // 'all' or default
            if ($current < $amount) {
                throw new Exception('رصيد الكوبونات الإجمالي غير كافٍ');
            }
            // Greedy subtraction from categories
            $rem = $amount;
            $att_sub = min($att_avail, $rem);
            $rem -= $att_sub;

            $com_sub = 0;
            if ($rem > 0) {
                $com_sub = min($com_avail, $rem);
                $rem -= $com_sub;
            }

            $tsk_sub = 0;
            if ($rem > 0) {
                $tsk_sub = min($tsk_avail, $rem);
                $rem -= $tsk_sub;
            }
            if ($rem > 0) {
                throw new Exception('رصيد الكوبونات الموزعة غير كافٍ');
            }
        }

        $newTotal = $current - $amount;

        // Update student
        $upd = $conn->prepare("UPDATE students SET coupons = ?, attendance_coupons = attendance_coupons - ?, commitment_coupons = commitment_coupons - ?, task_coupons = task_coupons - ? WHERE id = ?");
        $upd->bind_param("iiiii", $newTotal, $att_sub, $com_sub, $tsk_sub, $studentId);
        if (!$upd->execute()) {
            throw new Exception('فشل تحديث رصيد الطفل');
        }

        // Record history with breakdown
        $hist = $conn->prepare("INSERT INTO coupon_withdrawals (student_id, uncle_id, amount, att_amount, com_amount, tsk_amount, note) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $hist->bind_param("iiiiiis", $studentId, $uncleId, $amount, $att_sub, $com_sub, $tsk_sub, $note);
        if (!$hist->execute()) {
            throw new Exception('فشل تسجيل عملية السحب');
        }

        // ► Comprehensive Audit
        require_once 'audit.php';
        writeAuditLog('coupon_withdraw', 'coupon', $studentId, '', null, ['amount' => $amount], "سحب $amount كوبون" . ($note ? " ($note)" : ''));

        $conn->commit();
        sendJSON(['success' => true, 'message' => 'تم السحب بنجاح', 'newTotal' => $newTotal]);
    } catch (Exception $e) {
        if (isset($conn))
            $conn->rollback();
        sendJSON(['success' => false, 'message' => $e->getMessage()]);
    }

}



function getWithdrawalHistory()

{

    try {

        $conn = getDBConnection();

        $studentId = intval($_POST['student_id'] ?? 0);

        if ($studentId <= 0) {

            sendJSON(['success' => false, 'message' => 'id required']);

            return;

        }



        $stmt = $conn->prepare("

            SELECT w.*, u.name as uncle_name 

            FROM coupon_withdrawals w

            LEFT JOIN uncles u ON w.uncle_id = u.id

            WHERE w.student_id = ?

            ORDER BY w.created_at DESC

        ");

        $stmt->bind_param("i", $studentId);

        $stmt->execute();

        $res = $stmt->get_result();

        $history = [];

        while ($row = $res->fetch_assoc()) {

            $history[] = $row;

        }

        sendJSON(['success' => true, 'history' => $history]);

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



function refundWithdrawal()

{

    try {

        $conn = getDBConnection();

        $withdrawalId = intval($_POST['withdrawal_id'] ?? 0);

        $uncleId = $_SESSION['uncle_id'] ?? 0;



        if ($withdrawalId <= 0) {

            sendJSON(['success' => false, 'message' => 'بيانات غير صحيحة']);

            return;

        }



        $conn->begin_transaction();



        $stmt = $conn->prepare("SELECT * FROM coupon_withdrawals WHERE id = ? FOR UPDATE");

        $stmt->bind_param("i", $withdrawalId);

        $stmt->execute();

        $w = $stmt->get_result()->fetch_assoc();

        if (!$w) {

            throw new Exception('السحب غير موجود');

        }

        if ($w['is_refunded']) {

            throw new Exception('تم استرجاع هذا السحب بالفعل');

        }



        $amount = intval($w['amount']);

        $studentId = intval($w['student_id']);

        $att = intval($w['att_amount'] ?? 0);

        $com = intval($w['com_amount'] ?? 0);

        $tsk = intval($w['tsk_amount'] ?? 0);



        // Update student - restore breakdown as well

        $upd = $conn->prepare("UPDATE students SET coupons = coupons + ?, attendance_coupons = attendance_coupons + ?, commitment_coupons = commitment_coupons + ?, task_coupons = task_coupons + ? WHERE id = ?");

        $upd->bind_param("iiiii", $amount, $att, $com, $tsk, $studentId);

        if (!$upd->execute()) {

            throw new Exception('فشل استعادة الكوبونات للطفل');

        }



        // Mark as refunded

        $mark = $conn->prepare("UPDATE coupon_withdrawals SET is_refunded = 1, refunded_at = NOW() WHERE id = ?");

        $mark->bind_param("i", $withdrawalId);

        if (!$mark->execute()) {

            throw new Exception('فشل تحديث حالة السحب');

        }



        $conn->commit();



        // ► Comprehensive Audit

        require_once 'audit.php';

        writeAuditLog('coupon_refund', 'coupon', $studentId, '', null, ['amount' => $amount], "استرجاع $amount كوبون (عملية #$withdrawalId)");



        sendJSON(['success' => true, 'message' => 'تم استرجاع الكوبونات بنجاح']);

    } catch (Exception $e) {

        if (isset($conn))

            $conn->rollback();

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



function submitAttendance()

{

    try {

        $churchId = getChurchId();

        $uncleId = $_SESSION['uncle_id'] ?? null;

        $className = sanitize($_POST['className'] ?? '');

        $date = sanitize($_POST['date'] ?? '');

        $attendanceData = json_decode($_POST['attendanceData'] ?? '[]', true);



        // Validate date DD/MM/YYYY

        if (!preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {

            sendJSON(['success' => false, 'message' => 'تنسيق التاريخ غير صحيح. استخدم DD/MM/YYYY']);

            return;

        }

        $day = $matches[1];

        $month = $matches[2];

        $year = $matches[3];

        if (!checkdate($month, $day, $year)) {

            sendJSON(['success' => false, 'message' => 'تاريخ غير صحيح']);

            return;

        }

        $dbDate = "$year-$month-$day";



        if (empty($attendanceData)) {

            sendJSON(['success' => false, 'message' => 'لا توجد بيانات حضور']);

            return;

        }



        $conn = getDBConnection();

        $conn->begin_transaction();



        // ── Resolve class_id for this church dynamically ──────────

        // Supports both custom church_classes and global classes

        $resolvedClassId = null;



        // 1. Try custom church_classes by arabic_name

        $cChk = $conn->prepare("

            SELECT id FROM church_classes

            WHERE church_id = ? AND arabic_name = ? AND is_active = 1

            LIMIT 1

        ");

        $cChk->bind_param("is", $churchId, $className);

        $cChk->execute();

        if ($cRow = $cChk->get_result()->fetch_assoc()) {

            $resolvedClassId = (int) $cRow['id'];

        }



        // 2. Fall back to global classes by arabic_name

        if (!$resolvedClassId) {

            $gChk = $conn->prepare("SELECT id FROM classes WHERE arabic_name = ? LIMIT 1");

            $gChk->bind_param("s", $className);

            $gChk->execute();

            if ($gRow = $gChk->get_result()->fetch_assoc()) {

                $resolvedClassId = (int) $gRow['id'];

            }

        }



        error_log("submitAttendance: class='$className' resolved to ID=" . ($resolvedClassId ?? 'NULL'));



        $successCount = 0;



        foreach ($attendanceData as $record) {

            $studentName = sanitize($record['studentName']);

            $rawStatus = $record['status'] ?? 'pending';



            // 'pending' means the user cleared this student's attendance →

            // delete any existing record for them on this date

            if ($rawStatus === 'pending') {

                // Find the student first

                $student = null;

                if ($resolvedClassId) {

                    $s1 = $conn->prepare("SELECT id, name, attendance_coupons, commitment_coupons, task_coupons FROM students WHERE church_id = ? AND name = ? AND class_id = ?");

                    $s1->bind_param("isi", $churchId, $studentName, $resolvedClassId);

                    $s1->execute();

                    $student = $s1->get_result()->fetch_assoc();

                }

                if (!$student) {

                    $s2 = $conn->prepare("SELECT id, name, attendance_coupons, commitment_coupons, task_coupons FROM students WHERE church_id = ? AND name = ? AND class = ?");

                    $s2->bind_param("iss", $churchId, $studentName, $className);

                    $s2->execute();

                    $student = $s2->get_result()->fetch_assoc();

                }

                if (!$student) {

                    $s3 = $conn->prepare("SELECT id, name, attendance_coupons, commitment_coupons, task_coupons FROM students WHERE church_id = ? AND name = ? LIMIT 1");

                    $s3->bind_param("is", $churchId, $studentName);

                    $s3->execute();

                    $student = $s3->get_result()->fetch_assoc();

                }

                if ($student) {

                    $sid = $student['id'];

                    // Check if there was a 'present' record (need to reverse coupon)

                    $chkDel = $conn->prepare("SELECT status FROM attendance WHERE student_id = ? AND attendance_date = ?");

                    $chkDel->bind_param("is", $sid, $dbDate);

                    $chkDel->execute();

                    $existing = $chkDel->get_result()->fetch_assoc();

                    if ($existing) {

                        $del = $conn->prepare("DELETE FROM attendance WHERE student_id = ? AND attendance_date = ?");

                        $del->bind_param("is", $sid, $dbDate);

                        $del->execute();

                        // Reverse coupon if was present

                        if ($existing['status'] === 'present') {

                            $newAtt = max(0, intval($student['attendance_coupons']) - 100);

                            $newTotal = $newAtt + intval($student['commitment_coupons']) + intval($student['task_coupons']);

                            $upd = $conn->prepare("UPDATE students SET attendance_coupons=?, coupons=? WHERE id=?");

                            $upd->bind_param("iii", $newAtt, $newTotal, $sid);

                            $upd->execute();

                        }

                        $successCount++;

                    }

                }

                continue; // done with this pending record

            }



            $status = ($rawStatus === 'present') ? 'present' : 'absent';



            // ── Find student by name + church_id ─────────────────

            // Primary: match by class_id (handles custom classes)

            // Fallback: match by class text column (legacy)

            $student = null;



            if ($resolvedClassId) {

                $s1 = $conn->prepare("

                    SELECT id, name, attendance_coupons, commitment_coupons, task_coupons

                    FROM students 

                    WHERE church_id = ? AND name = ? AND class_id = ?

                ");

                $s1->bind_param("isi", $churchId, $studentName, $resolvedClassId);

                $s1->execute();

                $student = $s1->get_result()->fetch_assoc();

            }



            // Fallback: match by class text (legacy stored value)

            if (!$student) {

                $s2 = $conn->prepare("

                    SELECT id, name, attendance_coupons, commitment_coupons, task_coupons

                    FROM students 

                    WHERE church_id = ? AND name = ? AND class = ?

                ");

                $s2->bind_param("iss", $churchId, $studentName, $className);

                $s2->execute();

                $student = $s2->get_result()->fetch_assoc();

            }



            // Last fallback: just name + church_id (no class filter)

            if (!$student) {

                $s3 = $conn->prepare("

                    SELECT id, name, attendance_coupons, commitment_coupons, task_coupons

                    FROM students 

                    WHERE church_id = ? AND name = ?

                    LIMIT 1

                ");

                $s3->bind_param("is", $churchId, $studentName);

                $s3->execute();

                $student = $s3->get_result()->fetch_assoc();

            }



            if (!$student) {

                error_log("submitAttendance: Student '$studentName' not found in church $churchId");

                continue;

            }



            $studentId = $student['id'];

            $currentAttCoupons = intval($student['attendance_coupons']);

            $commitmentCoupons = intval($student['commitment_coupons']);

            $taskCoupons = intval($student['task_coupons']);



            // Check existing attendance

            $chkAtt = $conn->prepare("SELECT status FROM attendance WHERE student_id = ? AND attendance_date = ?");

            $chkAtt->bind_param("is", $studentId, $dbDate);

            $chkAtt->execute();

            $existing = $chkAtt->get_result()->fetch_assoc();



            // Upsert attendance

            $ins = $conn->prepare("

                INSERT INTO attendance (student_id, church_id, attendance_date, status, uncle_id)

                VALUES (?, ?, ?, ?, ?)

                ON DUPLICATE KEY UPDATE status = VALUES(status), uncle_id = VALUES(uncle_id)

            ");

            $ins->bind_param("iissi", $studentId, $churchId, $dbDate, $status, $uncleId);



            if ($ins->execute()) {

                $successCount++;



                // Coupon logic

                if ($status === 'present') {

                    if (!$existing || $existing['status'] !== 'present') {

                        $newAtt = $currentAttCoupons + 100;

                        $newTotal = $newAtt + $commitmentCoupons + $taskCoupons;

                        $upd = $conn->prepare("UPDATE students SET attendance_coupons=?, coupons=? WHERE id=?");

                        $upd->bind_param("iii", $newAtt, $newTotal, $studentId);

                        $upd->execute();

                    }

                } else {

                    if ($existing && $existing['status'] === 'present') {

                        $newAtt = max(0, $currentAttCoupons - 100);

                        $newTotal = $newAtt + $commitmentCoupons + $taskCoupons;

                        $upd = $conn->prepare("UPDATE students SET attendance_coupons=?, coupons=? WHERE id=?");

                        $upd->bind_param("iii", $newAtt, $newTotal, $studentId);

                        $upd->execute();

                    }

                }



                // Audit

                $isNew = ($existing === null);

                $oldStatus = $existing['status'] ?? '';

                if ($isNew || $oldStatus !== $status) {

                    auditAttendanceSave($studentId, $student['name'], $dbDate, $oldStatus, $status, $isNew);

                }

            }

        }



        $conn->commit();

        sendJSON([

            'success' => true,

            'message' => "تم حفظ الحضور بنجاح ($successCount طفل)",

            'savedCount' => $successCount,

            'date' => $date

        ]);



    } catch (Exception $e) {

        if (isset($conn))

            $conn->rollback();

        error_log("submitAttendance error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في حفظ الحضور: ' . $e->getMessage()]);

    }

}

function addStudent()

{

    try {

        $churchId = getChurchId();

        $name = sanitize($_POST['name'] ?? '');

        $classId = intval($_POST['classId'] ?? 0);

        $address = sanitize($_POST['address'] ?? '');

        $phone = $_POST['phone'] ?? '';

        $emergencyPhone = $_POST['emergency_phone'] ?? '';

        $medicalNotes = sanitize($_POST['medical_notes'] ?? '');

        $birthday = sanitize($_POST['birthday'] ?? '');

        $coupons = max(0, isset($_POST['coupons']) ? intval($_POST['coupons']) : 0);



        error_log("addStudent called:");

        error_log("Church ID: $churchId");

        error_log("Name: $name");

        error_log("Class ID: $classId");

        error_log("Raw Phone: " . $phone);

        error_log("Coupons: $coupons");



        if (empty($name) || $classId === 0) {

            sendJSON(['success' => false, 'message' => 'الاسم والفصل مطلوبان']);

            return;

        }



        // ── Phone cleaning ──────────────────────────────────────────

        // 1. Strip whitespace and leading quote characters

        $cleanPhone = preg_replace('/\s+/', '', $phone);

        $cleanPhone = preg_replace("/^'+/", '', $cleanPhone);



        // 2. Remove any non-digit characters EXCEPT the leading zero

        //    (keeps the number as a string, never casts to int)

        $cleanPhone = preg_replace('/[^\d]/', '', $cleanPhone);



        // 3. Enforce leading zero for Egyptian numbers

        if (!empty($cleanPhone) && substr($cleanPhone, 0, 1) !== '0') {

            $cleanPhone = '0' . $cleanPhone;

        }



        // 4. Validate Egyptian mobile format (01XXXXXXXXX = 11 digits)

        if (!empty($cleanPhone) && !preg_match('/^01[0-9]{9}$/', $cleanPhone)) {

            sendJSON(['success' => false, 'message' => 'رقم الهاتف يجب أن يبدأ بـ 01 ويتكون من 11 رقم']);

            return;

        }



        $cleanEmergencyPhone = preg_replace('/\s+/', '', $emergencyPhone);

        $cleanEmergencyPhone = preg_replace("/^'+/", '', $cleanEmergencyPhone);

        $cleanEmergencyPhone = preg_replace('/[^\d]/', '', $cleanEmergencyPhone);

        if (!empty($cleanEmergencyPhone) && substr($cleanEmergencyPhone, 0, 1) !== '0') {

            $cleanEmergencyPhone = '0' . $cleanEmergencyPhone;

        }

        if (!empty($cleanEmergencyPhone) && !preg_match('/^01[0-9]{9}$/', $cleanEmergencyPhone)) {

            sendJSON(['success' => false, 'message' => 'تليفون الطوارئ يجب أن يبدأ بـ 01 ويتكون من 11 رقم']);

            return;

        }



        error_log("Cleaned Phone: " . $cleanPhone);



        $conn = getDBConnection();



        // Check if student already exists

        $checkStmt = $conn->prepare("

            SELECT id FROM students 

            WHERE church_id = ? 

            AND LOWER(TRIM(name)) = LOWER(TRIM(?)) 

            AND class_id = ?

        ");

        $normalizedName = trim($name);

        $checkStmt->bind_param("isi", $churchId, $normalizedName, $classId);

        $checkStmt->execute();



        if ($checkStmt->get_result()->num_rows > 0) {

            sendJSON(['success' => false, 'message' => 'الطفل موجود بالفعل في هذا الفصل']);

            return;

        }



        // Format birthday

        $formattedBirthday = null;

        if (!empty($birthday)) {

            $formattedBirthday = formatDateToDB($birthday);

            if (!$formattedBirthday) {

                sendJSON(['success' => false, 'message' => 'تاريخ الميلاد غير صحيح. استخدم DD/MM/YYYY']);

                return;

            }

        }



        // Get class name — check church_classes first, then global classes

        $classStmt = $conn->prepare("

            SELECT arabic_name FROM church_classes 

            WHERE id = ? AND church_id = ? AND is_active = 1

            UNION

            SELECT arabic_name FROM classes 

            WHERE id = ? 

            LIMIT 1

        ");

        $classStmt->bind_param("iii", $classId, $churchId, $classId);

        $classStmt->execute();

        $classResult = $classStmt->get_result();

        $classData = $classResult->fetch_assoc();

        $className = $classData['arabic_name'] ?? '';



        // Handle photo upload

        $photoUrl = '';

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {

            $photoFilename = !empty($cleanPhone)

                ? "profile_{$cleanPhone}_" . time() . ".jpg"

                : "profile_" . preg_replace('/[^a-zA-Z0-9]/', '_', $name) . "_" . time() . ".jpg";



            $uploadDir = __DIR__ . '/uploads/students/';



            if (!is_dir($uploadDir)) {

                mkdir($uploadDir, 0755, true);

            }



            $uploadPath = $uploadDir . $photoFilename;



            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];

            $fileType = mime_content_type($_FILES['photo']['tmp_name']);



            if (in_array($fileType, $allowedTypes)) {

                if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {

                    // Apply AI enhancement if requested

                    $enhanceImage = isset($_POST['enhanceImage']) && $_POST['enhanceImage'] === 'true';

                    if ($enhanceImage) {

                        $enhancedPath = enhanceImage($uploadPath);

                        if ($enhancedPath) {

                            $uploadPath = $enhancedPath;

                            $photoFilename = basename($enhancedPath);

                        }

                    }

                    $photoUrl = "https://sunday-school.online/uploads/students/" . $photoFilename;

                }

            }

        }



        $totalCoupons = $coupons;



        error_log("Phone to store in DB: " . $cleanPhone);



        // Custom info field (one JSON value per church's custom field definition)

        $customInfoRaw = $_POST['custom_info'] ?? '';

        $customInfoJson = null;

        if (!empty(trim($customInfoRaw))) {

            $decoded = json_decode($customInfoRaw, true);

            $customInfoJson = is_array($decoded)

                ? json_encode($decoded, JSON_UNESCAPED_UNICODE)

                : json_encode(['field_0' => sanitize($customInfoRaw)], JSON_UNESCAPED_UNICODE);

        }



        $gender = sanitize($_POST['gender'] ?? '');

        if ($gender !== 'male' && $gender !== 'female') {

            $gender = detectGenderFromName($name);

        }



        // Insert student — phone stored as VARCHAR string, leading zero preserved

        if ($formattedBirthday === null) {

            $stmt = $conn->prepare("

                INSERT INTO students 

                (church_id, name, class_id, class, address, phone, emergency_phone, medical_notes,

                 commitment_coupons, coupons, attendance_coupons, image_url, custom_info, gender)

                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)

            ");

            safeBindParam(

                $stmt,

                $churchId,

                $name,

                $classId,

                $className,

                $address,

                $cleanPhone,

                $cleanEmergencyPhone,

                $medicalNotes,

                $coupons,

                $totalCoupons,

                $photoUrl,

                $customInfoJson,

                $gender

            );

        } else {

            $stmt = $conn->prepare("

                INSERT INTO students 

                (church_id, name, class_id, class, address, phone, emergency_phone, medical_notes, birthday, 

                 commitment_coupons, coupons, attendance_coupons, image_url, custom_info, gender)

                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)

            ");

            safeBindParam(

                $stmt,

                $churchId,

                $name,

                $classId,

                $className,

                $address,

                $cleanPhone,

                $cleanEmergencyPhone,

                $medicalNotes,

                $formattedBirthday,

                $coupons,

                $totalCoupons,

                $photoUrl,

                $customInfoJson,

                $gender

            );

        }



        if ($stmt->execute()) {

            $studentId = $conn->insert_id;



            // ► AUDIT

            auditStudentAdd($studentId, $name, [

                'name' => $name,

                'class' => $className,

                'class_id' => $classId,

                'phone' => $cleanPhone,

                'address' => $address,

                'birthday' => $formattedBirthday,

                'coupons' => $coupons,

            ]);



            sendJSON([

                'success' => true,

                'message' => 'تم إضافة الطفل بنجاح',

                'studentId' => $studentId,

                'photoUrl' => !empty($photoUrl) ? $photoUrl : null,

                'phone' => $cleanPhone

            ]);

        } else {

            error_log("❌ SQL Error: " . $stmt->error);

            sendJSON(['success' => false, 'message' => 'فشل في إضافة الطفل: ' . $stmt->error]);

        }



    } catch (Exception $e) {

        error_log("❌ addStudent error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في إضافة الطفل: ' . $e->getMessage()]);

    }

}



function updateStudentImageAfterCreation()

{

    try {

        $conn = getDBConnection();

        $studentId = intval($_POST['studentId'] ?? 0);



        if ($studentId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف الطفل مطلوب']);

            return;

        }



        $studentExistsStmt = $conn->prepare("SELECT id FROM students WHERE id = ? LIMIT 1");

        $studentExistsStmt->bind_param("i", $studentId);

        $studentExistsStmt->execute();

        if (!$studentExistsStmt->get_result()->fetch_assoc()) {

            sendJSON(['success' => false, 'message' => 'Student not found']);

            return;

        }



        // Handle photo upload

        $photoUrl = null;

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {

            error_log("Photo uploaded for update, starting upload process...");



            // Get student info for filename

            $infoStmt = $conn->prepare("SELECT name, phone FROM students WHERE id = ?");

            $infoStmt->bind_param("i", $studentId);

            $infoStmt->execute();

            $result = $infoStmt->get_result();



            if ($student = $result->fetch_assoc()) {

                $name = $student['name'];

                $phone = $student['phone'];



                // Clean phone for filename

                $cleanPhone = preg_replace('/[^\d]/', '', $phone);

                $photoFilename = !empty($cleanPhone) ?

                    "profile_{$cleanPhone}_" . time() . ".jpg" :

                    "profile_" . preg_replace('/[^a-zA-Z0-9]/', '_', $name) . "_" . time() . ".jpg";



                // Use the correct upload directory

                $uploadDir = __DIR__ . '/uploads/students/';



                // Create directory if it doesn't exist

                if (!is_dir($uploadDir)) {

                    mkdir($uploadDir, 0755, true);

                }



                $uploadPath = $uploadDir . $photoFilename;



                // Check file type

                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];

                $fileType = mime_content_type($_FILES['photo']['tmp_name']);



                if (!in_array($fileType, $allowedTypes)) {

                    sendJSON(['success' => false, 'message' => 'نوع الملف غير مسموح به. المسموح: JPG, PNG, GIF']);

                    return;

                }



                // Move uploaded file

                if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {

                    $photoUrl = "https://sunday-school.online/uploads/students/" . $photoFilename;

                    error_log("Photo uploaded successfully: $photoUrl");

                } else {

                    error_log("Failed to move uploaded file for update");

                }

            }

        } else {

            // If no file, use the provided URL

            $photoUrl = sanitize($_POST['imageUrl'] ?? '');

        }



        if (empty($photoUrl)) {

            sendJSON(['success' => false, 'message' => 'لا توجد صورة مرفوعة']);

            return;

        }



        // Update the student record

        $updateStmt = $conn->prepare("UPDATE students SET image_url = ? WHERE id = ?");

        $updateStmt->bind_param("si", $photoUrl, $studentId);



        if ($updateStmt->execute()) {

            $verifyStmt = $conn->prepare("SELECT image_url FROM students WHERE id = ? LIMIT 1");

            $verifyStmt->bind_param("i", $studentId);

            $verifyStmt->execute();

            $saved = $verifyStmt->get_result()->fetch_assoc();

            $savedUrl = $saved['image_url'] ?? '';



            if ($savedUrl !== $photoUrl) {

                sendJSON(['success' => false, 'message' => 'Photo uploaded but was not saved to the student profile']);

                return;

            }



            sendJSON([

                'success' => true,

                'message' => 'Photo updated successfully',

                'student_id' => $studentId,

                'imageUrl' => $savedUrl

            ]);

            return;

            sendJSON(['success' => true, 'message' => 'تم تحديث الصورة بنجاح']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في تحديث الصورة: ' . $updateStmt->error]);

        }



    } catch (Exception $e) {

        error_log("updateStudentImageAfterCreation error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحديث الصورة']);

    }

}

function updateStudent()

{

    try {

        $churchId = getChurchId();

        $studentId = intval($_POST['studentId'] ?? 0);

        $name = sanitize($_POST['name'] ?? '');

        $classId = intval($_POST['classId'] ?? 0);

        $address = sanitize($_POST['address'] ?? '');

        $phone = $_POST['phone'] ?? '';

        $emergencyPhone = $_POST['emergency_phone'] ?? '';

        $medicalNotes = sanitize($_POST['medical_notes'] ?? '');

        $birthday = sanitize($_POST['birthday'] ?? '');

        $coupons = max(0, intval($_POST['coupons'] ?? 0));



        if ($studentId === 0 || empty($name) || $classId === 0) {

            sendJSON(['success' => false, 'message' => 'بيانات غير كاملة']);

            return;

        }



        // Phone cleaning

        $cleanPhone = preg_replace('/\s+/', '', $phone);

        $cleanPhone = preg_replace("/^'+/", '', $cleanPhone);

        $cleanPhone = preg_replace('/[^\d]/', '', $cleanPhone);



        if (!empty($cleanPhone) && substr($cleanPhone, 0, 1) !== '0') {

            $cleanPhone = '0' . $cleanPhone;

        }



        if (!empty($cleanPhone) && !preg_match('/^01[0-9]{9}$/', $cleanPhone)) {

            sendJSON(['success' => false, 'message' => 'رقم الهاتف يجب أن يبدأ بـ 01 ويتكون من 11 رقم']);

            return;

        }



        $cleanEmergencyPhone = preg_replace('/\s+/', '', $emergencyPhone);

        $cleanEmergencyPhone = preg_replace("/^'+/", '', $cleanEmergencyPhone);

        $cleanEmergencyPhone = preg_replace('/[^\d]/', '', $cleanEmergencyPhone);

        if (!empty($cleanEmergencyPhone) && substr($cleanEmergencyPhone, 0, 1) !== '0') {

            $cleanEmergencyPhone = '0' . $cleanEmergencyPhone;

        }

        if (!empty($cleanEmergencyPhone) && !preg_match('/^01[0-9]{9}$/', $cleanEmergencyPhone)) {

            sendJSON(['success' => false, 'message' => 'تليفون الطوارئ يجب أن يبدأ بـ 01 ويتكون من 11 رقم']);

            return;

        }



        $conn = getDBConnection();



        // Check student exists in this church

        ensureStudentSiblingGroupTables($conn);



        $checkStmt = $conn->prepare("

            SELECT id, attendance_coupons, custom_info 

            FROM students 

            WHERE id = ? AND church_id = ?

        ");

        $checkStmt->bind_param("ii", $studentId, $churchId);

        $checkStmt->execute();

        $result = $checkStmt->get_result();



        if ($result->num_rows === 0) {

            sendJSON(['success' => false, 'message' => 'لم يتم العثور على الطفل']);

            return;

        }



        $row = $result->fetch_assoc();

        $attendanceCoupons = intval($row['attendance_coupons']);

        $existingCustomInfoRaw = $row['custom_info'] ?? null;



        // Check duplicate name in same class (exclude current student)

        $duplicateStmt = $conn->prepare("

            SELECT id FROM students 

            WHERE church_id = ? 

            AND LOWER(TRIM(name)) = LOWER(TRIM(?)) 

            AND class_id = ? 

            AND id != ?

        ");

        $normalizedName = trim($name);

        $duplicateStmt->bind_param("isii", $churchId, $normalizedName, $classId, $studentId);

        $duplicateStmt->execute();



        if ($duplicateStmt->get_result()->num_rows > 0) {

            sendJSON(['success' => false, 'message' => 'اسم الطفل موجود بالفعل في هذا الفصل']);

            return;

        }



        // Format birthday

        $formattedBirthday = null;

        if (!empty($birthday)) {

            $formattedBirthday = formatDateToDB($birthday);

            if (!$formattedBirthday) {

                sendJSON(['success' => false, 'message' => 'تاريخ الميلاد غير صحيح']);

                return;

            }

        }



        // Get class name — church_classes first, then global

        $classStmt = $conn->prepare("

            SELECT arabic_name FROM church_classes 

            WHERE id = ? AND church_id = ? AND is_active = 1

            UNION

            SELECT arabic_name FROM classes 

            WHERE id = ?

            LIMIT 1

        ");

        $classStmt->bind_param("iii", $classId, $churchId, $classId);

        $classStmt->execute();

        $classResult = $classStmt->get_result();

        $classData = $classResult->fetch_assoc();

        $className = $classData['arabic_name'] ?? '';



        $currentCouponsStmt = $conn->prepare("

            SELECT attendance_coupons, commitment_coupons, task_coupons

            FROM students WHERE id = ?

        ");

        $currentCouponsStmt->bind_param("i", $studentId);

        $currentCouponsStmt->execute();

        $currentData = $currentCouponsStmt->get_result()->fetch_assoc();

        $attendanceCoupons = intval($currentData['attendance_coupons']);

        $taskCoupons = intval($currentData['task_coupons'] ?? 0);



        // Get snapshot BEFORE update

        $beforeSnapshot = getStudentSnapshot($studentId);



        // $coupons from POST = the new commitment_coupons value

        $totalCoupons = $attendanceCoupons + $coupons + $taskCoupons;



        error_log("Coupons calculation: attendance=$attendanceCoupons + commitment=$coupons + task=$taskCoupons = total=$totalCoupons");



        // Custom info — merge with existing; sibling links always come from DB table by student id

        $customInfoRaw = $_POST['custom_info'] ?? null;

        $customInfoJson = null;

        if ($customInfoRaw !== null) {

            if (trim($customInfoRaw) === '') {

                $customInfoJson = mergeStudentCustomInfoForUpdate($conn, $studentId, $existingCustomInfoRaw, []);

            } else {

                $decoded = json_decode($customInfoRaw, true);

                if (!is_array($decoded)) {

                    $decoded = ['field_0' => sanitize($customInfoRaw)];

                }

                $customInfoJson = mergeStudentCustomInfoForUpdate($conn, $studentId, $existingCustomInfoRaw, $decoded);

            }

        }



        $gender = sanitize($_POST['gender'] ?? '');

        if ($gender !== 'male' && $gender !== 'female') {

            $gender = detectGenderFromName($name);

        }



        if ($customInfoRaw !== null) {

            $updateStmt = $conn->prepare("

                UPDATE students 

                SET name = ?, class_id = ?, class = ?, address = ?, phone = ?, emergency_phone = ?, medical_notes = ?, birthday = ?, 

                    commitment_coupons = ?, coupons = ?, custom_info = ?, gender = ?, updated_at = NOW()

                WHERE id = ? AND church_id = ?

            ");

            safeBindParam(

                $updateStmt,

                $name,

                $classId,

                $className,

                $address,

                $cleanPhone,

                $cleanEmergencyPhone,

                $medicalNotes,

                $formattedBirthday,

                $coupons,

                $totalCoupons,

                $customInfoJson,

                $gender,

                $studentId,

                $churchId

            );

        } else {

            $updateStmt = $conn->prepare("

                UPDATE students 

                SET name = ?, class_id = ?, class = ?, address = ?, phone = ?, emergency_phone = ?, medical_notes = ?, birthday = ?, 

                    commitment_coupons = ?, coupons = ?, gender = ?, updated_at = NOW()

                WHERE id = ? AND church_id = ?

            ");

            safeBindParam(

                $updateStmt,

                $name,

                $classId,

                $className,

                $address,

                $cleanPhone,

                $cleanEmergencyPhone,

                $medicalNotes,

                $formattedBirthday,

                $coupons,

                $totalCoupons,

                $gender,

                $studentId,

                $churchId

            );

        }



        if ($updateStmt->execute()) {

            // Get snapshot AFTER update

            $afterSnapshot = getStudentSnapshot($studentId);



            // ► AUDIT

            auditStudentEdit($studentId, $beforeSnapshot ?? [], $afterSnapshot ?? []);



            sendJSON([

                'success' => true,

                'message' => 'تم تحديث معلومات الطفل بنجاح',

                'studentId' => $studentId,

                'phone' => $cleanPhone

            ]);

        } else {

            error_log("❌ Update failed: " . $conn->error);

            sendJSON(['success' => false, 'message' => 'فشل في تحديث الطفل: ' . $conn->error]);

        }

    } catch (Exception $e) {

        error_log("updateStudent error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحديث الطفل: ' . $e->getMessage()]);

    }

}



function reviewStudentGender()

{

        try {

            $churchId = getChurchId();

            $studentId = intval($_POST['student_id'] ?? 0);

            $action = sanitize($_POST['action'] ?? '');

            $suggestedGender = sanitize($_POST['suggested_gender'] ?? '');



            if ($studentId === 0 || !in_array($action, ['approve', 'reject'], true)) {

                sendJSON(['success' => false, 'message' => 'بيانات المراجعة غير صحيحة']);

                return;

            }



            $conn = getDBConnection();

            $checkStmt = $conn->prepare("SELECT custom_info FROM students WHERE id = ? AND church_id = ?");

            $checkStmt->bind_param('ii', $studentId, $churchId);

            $checkStmt->execute();

            $result = $checkStmt->get_result();

            if ($result->num_rows === 0) {

                sendJSON(['success' => false, 'message' => 'لم يتم العثور على الطفل']);

                return;

            }



            $row = $result->fetch_assoc();

            $customInfo = json_decode($row['custom_info'] ?? 'null', true);

            if (!is_array($customInfo)) {

                $customInfo = [];

            }



            if ($action === 'approve') {

                if ($suggestedGender !== 'male' && $suggestedGender !== 'female') {

                    sendJSON(['success' => false, 'message' => 'النوع المقترح غير صالح']);

                    return;

                }

                $customInfo['gender_review_status'] = 'approved';

                $customInfo['gender_suggestion'] = $suggestedGender;

                $customInfoJson = json_encode($customInfo, JSON_UNESCAPED_UNICODE);

                $updateStmt = $conn->prepare("UPDATE students SET gender = ?, custom_info = ?, updated_at = NOW() WHERE id = ? AND church_id = ?");

                $updateStmt->bind_param('ssii', $suggestedGender, $customInfoJson, $studentId, $churchId);

            } else {

                $customInfo['gender_review_status'] = 'rejected';

                $customInfoJson = json_encode($customInfo, JSON_UNESCAPED_UNICODE);

                $updateStmt = $conn->prepare("UPDATE students SET custom_info = ?, updated_at = NOW() WHERE id = ? AND church_id = ?");

                $updateStmt->bind_param('sii', $customInfoJson, $studentId, $churchId);

            }



            if ($updateStmt->execute()) {

                sendJSON(['success' => true, 'message' => 'تم تحديث مراجعة النوع بنجاح']);

            } else {

                error_log("❌ reviewStudentGender failed: " . $conn->error);

                sendJSON(['success' => false, 'message' => 'فشل في حفظ مراجعة النوع']);

            }

        } catch (Exception $e) {

            sendJSON(['success' => false, 'message' => 'خطأ في الخادم']);

        }

    }

function updateStudentInfo()

{

    try {

        $studentId = intval($_POST['studentId'] ?? 0);

        $name = sanitize($_POST['name'] ?? '');

        $address = sanitize($_POST['address'] ?? '');

        $phone = sanitize($_POST['phone'] ?? '');

        $birthday = sanitize($_POST['birthday'] ?? '');



        if ($studentId === 0 || empty($name)) {

            sendJSON(['success' => false, 'message' => 'بيانات غير كاملة']);

            return;

        }



        // Format birthday using our function

        $formattedBirthday = null;

        if (!empty($birthday)) {

            $formattedBirthday = formatDateToDB($birthday);

            if (!$formattedBirthday) {

                sendJSON(['success' => false, 'message' => 'تاريخ الميلاد غير صحيح. استخدم DD/MM/YYYY']);

                return;

            }

        }



        $conn = getDBConnection();



        // Check if phone is already used by another student

        if (!empty($phone)) {

            $cleanPhone = preg_replace('/[^\d]/', '', $phone);



            $checkPhoneStmt = $conn->prepare("

                SELECT id FROM students 

                WHERE phone LIKE CONCAT('%', ?) AND id != ?

            ");

            $checkPhoneStmt->bind_param("si", $cleanPhone, $studentId);

            $checkPhoneStmt->execute();



            if ($checkPhoneStmt->get_result()->num_rows > 0) {

                sendJSON(['success' => false, 'message' => 'رقم الهاتف مستخدم بالفعل من قبل طفل آخر']);

                return;

            }

        }



        $gender = sanitize($_POST['gender'] ?? '');

        if ($gender !== 'male' && $gender !== 'female') {

            $gender = detectGenderFromName($name);

        }



        // Update student information

        $stmt = $conn->prepare("

            UPDATE students 

            SET name = ?, address = ?, phone = ?, birthday = ?, gender = ?, updated_at = NOW()

            WHERE id = ?

        ");

        $stmt->bind_param(

            "sssssi",

            $name,

            $address,

            $phone,

            $formattedBirthday,

            $gender,

            $studentId

        );



        if ($stmt->execute()) {

            // Update password if provided

            if (isset($_POST['password']) && !empty($_POST['password'])) {

                $password = $_POST['password'];

                $passwordHash = hash('sha256', $password);



                $updatePassStmt = $conn->prepare("

                    UPDATE students 

                    SET password_hash = ?

                    WHERE id = ?

                ");

                $updatePassStmt->bind_param("si", $passwordHash, $studentId);

                $updatePassStmt->execute();

            }



            sendJSON([

                'success' => true,

                'message' => 'تم تحديث المعلومات بنجاح'

            ]);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في تحديث المعلومات: ' . $conn->error]);

        }



    } catch (Exception $e) {

        error_log("updateStudentInfo error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحديث المعلومات: ' . $e->getMessage()]);

    }

}



function updateCoupons()

{

    try {

        error_log("🔄 updateCoupons called");



        $churchId = getChurchId();

        $className = sanitize($_POST['className'] ?? '');

        $couponData = json_decode($_POST['couponData'] ?? '[]', true);



        error_log("Church ID: $churchId, Class: $className");

        error_log("Coupon Data: " . json_encode($couponData));



        if (empty($couponData)) {

            sendJSON(['success' => false, 'message' => 'لا توجد بيانات كوبونات']);

            return;

        }



        $conn = getDBConnection();

        $successCount = 0;

        $failCount = 0;



        foreach ($couponData as $record) {

            $studentName = sanitize($record['studentName'] ?? '');

            $coupons = max(0, intval($record['coupons'] ?? 0));



            error_log("Updating: $studentName -> $coupons coupons");



            // Search by student name and church_id only (no class JOIN that might fail)

            $stmt = $conn->prepare("

                SELECT id, coupons, attendance_coupons, task_coupons

                FROM students 

                WHERE church_id = ? AND name = ?

            ");

            $stmt->bind_param("is", $churchId, $studentName);

            $stmt->execute();

            $result = $stmt->get_result();



            if ($row = $result->fetch_assoc()) {

                $studentId = $row['id'];

                $oldTotal = intval($row['coupons']);

                $attendanceCoupons = intval($row['attendance_coupons']);

                $taskCoupons = intval($row['task_coupons'] ?? 0);

                $totalCoupons = $attendanceCoupons + $coupons + $taskCoupons;



                $updateStmt = $conn->prepare("

                    UPDATE students 

                    SET commitment_coupons = ?,

                        coupons = ?,

                        updated_at = NOW()

                    WHERE id = ? AND church_id = ?

                ");

                $updateStmt->bind_param("iiii", $coupons, $totalCoupons, $studentId, $churchId);



                if ($updateStmt->execute()) {

                    error_log("✅ Updated $studentName: commitment=$coupons, total=$totalCoupons");

                    // ► AUDIT

                    auditCouponChange($studentId, $studentName, $oldTotal, $totalCoupons, 'تعديل دفعة (التزام)');

                    $successCount++;

                } else {

                    error_log("❌ Failed to update $studentName: " . $updateStmt->error);

                    $failCount++;

                }

            } else {

                error_log("⚠️ Student not found: $studentName in church $churchId");

                $failCount++;

            }

        }



        if ($successCount > 0) {

            sendJSON([

                'success' => true,

                'message' => "تم تحديث كوبونات $successCount طفل بنجاح" . ($failCount > 0 ? " (فشل $failCount)" : ''),

                'updated' => $successCount,

                'failed' => $failCount

            ]);

        } else {

            sendJSON([

                'success' => false,

                'message' => 'لم يتم تحديث أي كوبونات - تحقق من أسماء الأطفال',

                'updated' => 0,

                'failed' => $failCount

            ]);

        }



    } catch (Exception $e) {

        error_log("❌ updateCoupons error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحديث الكوبونات: ' . $e->getMessage()]);

    }

}

// ===== GET CHURCH CLASSES WITH STUDENT COUNT =====

function getChurchClassesWithStats()

{

    try {

        $churchId = getChurchId();



        $conn = getDBConnection();



        // تحقق من وجود فصول مخصصة

        $checkCustom = $conn->prepare("SELECT COUNT(*) as cnt FROM church_classes WHERE church_id = ?");

        $checkCustom->bind_param("i", $churchId);

        $checkCustom->execute();

        $hasCustom = $checkCustom->get_result()->fetch_assoc()['cnt'] > 0;



        if ($hasCustom) {

            // فصول مخصصة مع عدد الأطفال

            $stmt = $conn->prepare("

                SELECT 

                    cc.id,

                    cc.code,

                    cc.arabic_name,

                    cc.display_order,

                    COUNT(s.id) as student_count

                FROM church_classes cc

                LEFT JOIN students s ON s.class_id = cc.id AND s.church_id = ?

                WHERE cc.church_id = ? AND cc.is_active = 1

                GROUP BY cc.id

                ORDER BY cc.display_order

            ");

            $stmt->bind_param("ii", $churchId, $churchId);

        } else {

            // فصول افتراضية مع عدد الأطفال

            $stmt = $conn->prepare("

                SELECT 

                    c.id,

                    c.code,

                    c.arabic_name,

                    c.display_order,

                    COUNT(s.id) as student_count

                FROM classes c

                LEFT JOIN students s ON s.class_id = c.id AND s.church_id = ?

                GROUP BY c.id

                ORDER BY c.display_order

            ");

            $stmt->bind_param("i", $churchId);

        }



        $stmt->execute();

        $result = $stmt->get_result();



        $classes = [];

        while ($row = $result->fetch_assoc()) {

            $classes[] = [

                'id' => (int) $row['id'],

                'code' => $row['code'],

                'arabic_name' => $row['arabic_name'],

                'display_order' => (int) $row['display_order'],

                'student_count' => (int) $row['student_count'],

                'is_custom' => $hasCustom

            ];

        }



        sendJSON([

            'success' => true,

            'classes' => $classes,

            'has_custom' => $hasCustom

        ]);



    } catch (Exception $e) {

        error_log("getChurchClassesWithStats error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحميل الفصول']);

    }

}

// ===== GET PUBLIC CHURCH CLASSES (للتسجيل العام) =====

function getPublicChurchClasses()

{

    try {

        $churchId = intval($_POST['church_id'] ?? $_GET['church_id'] ?? 0);



        if ($churchId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف الكنيسة مطلوب']);

            return;

        }



        $conn = getDBConnection();



        // أولاً: تحقق من وجود فصول مخصصة لهذه الكنيسة

        $customStmt = $conn->prepare("

            SELECT COUNT(*) as count 

            FROM church_classes 

            WHERE church_id = ? AND is_active = 1

        ");

        $customStmt->bind_param("i", $churchId);

        $customStmt->execute();

        $customResult = $customStmt->get_result();

        $hasCustom = $customResult->fetch_assoc()['count'] > 0;



        if ($hasCustom) {

            // إذا وجدت فصول مخصصة، أرجعها

            $classStmt = $conn->prepare("

                SELECT id, code, arabic_name, display_order

                FROM church_classes 

                WHERE church_id = ? AND is_active = 1

                ORDER BY display_order, arabic_name

            ");

            $classStmt->bind_param("i", $churchId);

        } else {

            // إذا لم توجد فصول مخصصة، أرجع الفصول الافتراضية من جدول classes

            $classStmt = $conn->prepare("

                SELECT id, code, arabic_name, display_order

                FROM classes 

                ORDER BY display_order

            ");

        }



        $classStmt->execute();

        $result = $classStmt->get_result();



        $classes = [];

        while ($row = $result->fetch_assoc()) {

            $classes[] = [

                'id' => $row['id'],

                'code' => $row['code'],

                'arabic_name' => $row['arabic_name'],

                'display_order' => $row['display_order'],

                'is_custom' => $hasCustom

            ];

        }



        // Load custom registration fields for this church

        $customFields = [];

        try {

            $cfStmt = $conn->prepare("SELECT custom_field FROM church_settings WHERE church_id = ? LIMIT 1");

            $cfStmt->bind_param("i", $churchId);

            $cfStmt->execute();

            $cfRow = $cfStmt->get_result()->fetch_assoc();

            if ($cfRow && !empty($cfRow['custom_field'])) {

                $decoded = json_decode($cfRow['custom_field'], true);

                if (is_array($decoded))

                    $customFields = $decoded;

            }

        } catch (Exception $cfErr) {

            error_log("getPublicChurchClasses custom_field error: " . $cfErr->getMessage());

        }



        sendJSON([

            'success' => true,

            'classes' => $classes,

            'has_custom' => $hasCustom,

            'custom_fields' => $customFields,

            'message' => $hasCustom ? 'تم تحميل الفصول المخصصة' : 'تم تحميل الفصول الافتراضية'

        ]);



    } catch (Exception $e) {

        error_log("getPublicChurchClasses error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحميل الفصول']);

    }

}

// ===== FIXED: DELETE STUDENT =====

function deleteStudent()

{

    try {

        $churchId = getChurchId();

        $studentId = intval($_POST['studentId'] ?? 0);



        if ($studentId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف الطفل مطلوب']);

            return;

        }



        $conn = getDBConnection();



        // First, get student info for logging

        $getStmt = $conn->prepare("

            SELECT s.name, s.image_url, c.arabic_name as class 

            FROM students s

            LEFT JOIN classes c ON s.class_id = c.id

            WHERE s.id = ? AND s.church_id = ?

        ");

        $getStmt->bind_param("ii", $studentId, $churchId);

        $getStmt->execute();

        $result = $getStmt->get_result();



        if ($result->num_rows === 0) {

            sendJSON(['success' => false, 'message' => 'لم يتم العثور على الطفل']);

            return;

        }



        $student = $result->fetch_assoc();

        $studentName = $student['name'];

        $className = $student['class'];

        $studentImageUrl = $student['image_url'] ?? null;



        // Start transaction

        $conn->begin_transaction();



        try {

            // Get snapshot BEFORE delete

            $studentSnapshot = getStudentSnapshot($studentId);



            $deleteAttendanceStmt = $conn->prepare("

                DELETE FROM attendance 

                WHERE student_id = ?

            ");

            $deleteAttendanceStmt->bind_param("i", $studentId);

            $deleteAttendanceStmt->execute();



            // 2. Delete coupon logs if table exists

            $tableCheck = $conn->query("SHOW TABLES LIKE 'coupon_logs'");

            if ($tableCheck && $tableCheck->num_rows > 0) {

                $deleteLogsStmt = $conn->prepare("

                    DELETE FROM coupon_logs 

                    WHERE student_id = ?

                ");

                $deleteLogsStmt->bind_param("i", $studentId);

                $deleteLogsStmt->execute();

            }



            // 3. Delete the student

            $deleteStmt = $conn->prepare("

                DELETE FROM students 

                WHERE id = ? AND church_id = ?

            ");

            $deleteStmt->bind_param("ii", $studentId, $churchId);



            if ($deleteStmt->execute()) {

                if ($deleteStmt->affected_rows > 0) {

                    $conn->commit();



                    // Clean up student image file from disk

                    deleteUploadedFile($studentImageUrl);



                    // Log the deletion

                    error_log("Student deleted - ID: $studentId, Name: $studentName, Class: $className, Church: $churchId");



                    // ► AUDIT

                    auditStudentDelete($studentId, $studentSnapshot ?? ['id' => $studentId, 'name' => $studentName]);



                    sendJSON([

                        'success' => true,

                        'message' => 'تم حذف الطفل "' . $studentName . '" من فصل "' . $className . '" بنجاح',

                        'studentId' => $studentId

                    ]);

                } else {

                    $conn->rollback();

                    sendJSON(['success' => false, 'message' => 'لم يتم العثور على الطفل أو لا يوجد صلاحية للحذف']);

                }

            } else {

                $conn->rollback();

                error_log("Delete failed: " . $conn->error);

                sendJSON(['success' => false, 'message' => 'فشل في حذف الطفل: ' . $conn->error]);

            }



        } catch (Exception $e) {

            $conn->rollback();

            throw $e;

        }



    } catch (Exception $e) {

        error_log("deleteStudent error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في حذف الطفل: ' . $e->getMessage()]);

    }

}



// ===== UPDATE STUDENT IMAGE =====

function updateStudentImage()

{

    checkUncleAuth();



    try {

        $studentId = intval($_POST['student_id'] ?? $_POST['studentId'] ?? 0);

        $imageUrl = sanitize($_POST['imageUrl'] ?? '');



        if ($studentId === 0 || empty($imageUrl)) {

            sendJSON(['success' => false, 'message' => 'بيانات غير كاملة']);

            return;

        }



        $conn = getDBConnection();

        $stmt = $conn->prepare("UPDATE students SET image_url = ? WHERE id = ?");

        $stmt->bind_param("si", $imageUrl, $studentId);



        if ($stmt->execute()) {

            sendJSON(['success' => true, 'message' => 'تم تحديث صورة الطفل بنجاح', 'student_id' => $studentId, 'imageUrl' => $imageUrl]);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في تحديث صورة الطفل: ' . $conn->error]);

        }



    } catch (Exception $e) {

        error_log("updateStudentImage error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحديث صورة الطفل']);

    }

}



// ===== GET ALL ANNOUNCEMENTS =====

function getAllAnnouncements()

{

    try {

        $churchId = getChurchId();

        $cairoTimeZone = '+02:00';



        $conn = getDBConnection();

        $stmt = $conn->prepare("\n            SELECT id, type, text as 'النص', link as 'الرابط', class as 'الفصل', student_names as 'أسماء الأطفال', is_active as 'منشط',\n            DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', ?), '%d/%m/%Y %h:%i %p') as 'تاريخ الإضافة'\n            FROM announcements \n            WHERE church_id = ?\n            ORDER BY created_at DESC\n        ");

        $stmt->bind_param("si", $cairoTimeZone, $churchId);

        $stmt->execute();

        $result = $stmt->get_result();



        $announcements = [];

        while ($row = $result->fetch_assoc()) {

            if (!empty($row['تاريخ الإضافة'])) {

                $row['تاريخ الإضافة'] = str_replace(['AM', 'PM'], ['صباحاً', 'مساءً'], $row['تاريخ الإضافة']);

            }

            $announcements[] = $row;

        }



        sendJSON(['success' => true, 'announcements' => $announcements]);



    } catch (Exception $e) {

        error_log("getAllAnnouncements error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في جلب الإعلانات']);

    }

}



function addAnnouncement()

{

    try {

        $churchId = getChurchId();

        $type = sanitize($_POST['type'] ?? 'message');

        $text = sanitize($_POST['text'] ?? '');

        $link = sanitize($_POST['link'] ?? '');

        $students = sanitize($_POST['students'] ?? '');

        // Accept either `classes` (array/JSON/comma-list) or legacy `class` single value.
        $classesRaw = $_POST['classes'] ?? $_POST['class'] ?? 'الجميع';
        $classes = [];
        if (is_array($classesRaw)) {
            $classes = $classesRaw;
        } else {
            $str = trim((string) $classesRaw);
            // Try JSON first
            if ($str !== '' && ($str[0] === '[' || $str[0] === '{')) {
                $decoded = json_decode($str, true);
                if (is_array($decoded)) $classes = $decoded;
            }
            if (empty($classes)) {
                // Fallback: split comma or pipe separated lists
                $parts = preg_split('/[,|]+/', $str);
                $parts = array_map('trim', $parts ?: []);
                $classes = array_values(array_filter($parts, fn($v) => $v !== ''));
            }
        }
        if (empty($classes)) $classes = ['الجميع'];

        // Validate classes list
        $allowedClasses = ['الجميع', 'حضانة', 'أولى', 'تانية', 'تالتة', 'رابعة', 'خامسة', 'سادسة'];
        foreach ($classes as $c) {
            if (!in_array($c, $allowedClasses)) {
                sendJSON(['success' => false, 'message' => 'الفصل غير صالح: ' . $c]);
                return;
            }
        }
        // Normalize stored class value: single 'الجميع' or comma-separated list
        $class = in_array('الجميع', $classes, true) ? 'الجميع' : implode(',', array_unique($classes));



        $conn = getDBConnection();



        // Insert with current timestamp (UTC)

        $stmt = $conn->prepare("

            INSERT INTO announcements (church_id, type, text, link, class, student_names, created_at)

            VALUES (?, ?, ?, ?, ?, ?, NOW())

        ");

        $stmt->bind_param("isssss", $churchId, $type, $text, $link, $class, $students);



        if ($stmt->execute()) {

            $insertedId = $conn->insert_id;



            // Fetch the created timestamp converted to Cairo time

            $cairoTimeZone = '+02:00';

            $timeStmt = $conn->prepare("

                SELECT DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', ?), '%d/%m/%Y %h:%i %p') as added_time 

                FROM announcements 

                WHERE id = ?

            ");

            $timeStmt->bind_param("si", $cairoTimeZone, $insertedId);

            $timeStmt->execute();

            $timeResult = $timeStmt->get_result();

            $timeData = $timeResult->fetch_assoc();



            // Get current Cairo time as fallback

            $cairoTime = new DateTime('now', new DateTimeZone('Africa/Cairo'));

            $defaultTime = $cairoTime->format('d/m/Y h:i A');



            $addedTime = $timeData['added_time'] ?? $defaultTime;

            // Replace AM/PM with Arabic

            $addedTime = str_replace(['AM', 'PM'], ['صباحاً', 'مساءً'], $addedTime);



            // ► AUDIT

            auditAnnouncementAdd($insertedId, $text);



            sendJSON([

                'success' => true,

                'message' => 'تم إضافة الإعلان بنجاح',

                'added_at' => $addedTime,

                'announcement_id' => $insertedId

            ]);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في إضافة الإعلان: ' . $stmt->error]);

        }



    } catch (Exception $e) {

        error_log("addAnnouncement error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في إضافة الإعلان: ' . $e->getMessage()]);

    }

}

function toggleAnnouncement()

{

    try {

        $churchId = getChurchId();

        $rowIndex = intval($_POST['rowIndex'] ?? 0);

        $active = $_POST['active'] === 'true' ? 1 : 0;



        $conn = getDBConnection();

        $stmt = $conn->prepare("UPDATE announcements SET is_active = ? WHERE id = ? AND church_id = ?");

        $stmt->bind_param("iii", $active, $rowIndex, $churchId);



        if ($stmt->execute()) {

            // ► AUDIT

            auditAnnouncementToggle($rowIndex, (bool) $active);



            sendJSON(['success' => true, 'message' => 'تم تحديث الحالة بنجاح']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في تحديث الحالة']);

        }



    } catch (Exception $e) {

        error_log("toggleAnnouncement error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحديث الإعلان']);

    }

}



function deleteAnnouncement()

{

    try {

        $churchId = getChurchId();

        $rowIndex = intval($_POST['rowIndex'] ?? 0);



        $conn = getDBConnection();



        // BEFORE delete, get old announcement data

        $oldAnnouncement = getAnnouncementSnapshot($rowIndex);



        $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ? AND church_id = ?");

        $stmt->bind_param("ii", $rowIndex, $churchId);



        if ($stmt->execute()) {

            // ► AUDIT

            auditAnnouncementDelete($rowIndex, $oldAnnouncement ?? ['id' => $rowIndex]);



            sendJSON(['success' => true, 'message' => 'تم حذف الإعلان بنجاح']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في حذف الإعلان']);

        }



    } catch (Exception $e) {

        error_log("deleteAnnouncement error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في حذف الإعلان']);

    }

}



function getStudentByPhone()

{

    try {

        $phone = sanitize($_POST['phone'] ?? $_GET['phone'] ?? '');



        if (empty($phone)) {

            sendJSON(['success' => false, 'message' => 'رقم الهاتف مطلوب']);

        }



        // Clean the phone number - remove all non-numeric characters

        $cleanPhone = preg_replace('/[^\d]/', '', $phone);



        // Log for debugging

        error_log("Searching for phone: $phone (cleaned: $cleanPhone)");



        $conn = getDBConnection();



        $stmt = $conn->prepare("

            SELECT 

                s.id, s.name, s.address, s.phone, s.birthday,

                s.coupons, s.attendance_coupons, s.commitment_coupons,

                s.image_url, s.church_id, s.class_id,

                c.church_name,

                cl.arabic_name as class

            FROM students s

            LEFT JOIN churches c ON s.church_id = c.id

            LEFT JOIN classes cl ON s.class_id = cl.id

            WHERE s.phone = ? 

               OR s.phone LIKE CONCAT('%', ?)

               OR REPLACE(s.phone, '''', '') = ?

               OR REPLACE(s.phone, '''', '') LIKE CONCAT('%', ?)

               OR REPLACE(REPLACE(s.phone, '''', ''), ' ', '') = ?

               OR REPLACE(REPLACE(s.phone, '''', ''), ' ', '') LIKE CONCAT('%', ?)

        ");



        $stmt->bind_param(

            "ssssss",

            $cleanPhone,

            $cleanPhone,

            $cleanPhone,

            $cleanPhone,

            $cleanPhone,

            $cleanPhone

        );

        $stmt->execute();

        $result = $stmt->get_result();



        $students = [];

        while ($row = $result->fetch_assoc()) {

            // تنسيق تاريخ الميلاد

            $row['birthday'] = formatDateFromDB($row['birthday']);

            $row['class'] = $row['class'];

            $students[] = $row;

        }



        error_log("Found " . count($students) . " students for phone: $cleanPhone");



        if (count($students) > 0) {

            sendJSON([

                'success' => true,

                'data' => $students,

                'message' => 'تم العثور على ' . count($students) . ' طفل'

            ]);

        } else {

            // Try one more time with just the last 9 digits

            $last9Digits = substr($cleanPhone, -9);

            if (strlen($last9Digits) >= 9) {

                error_log("Trying with last 9 digits: $last9Digits");



                $stmt2 = $conn->prepare("

                    SELECT 

                        s.id, s.name, s.address, s.phone, s.birthday,

                        s.coupons, s.attendance_coupons, s.commitment_coupons,

                        s.image_url, s.church_id, s.class_id,

                        c.church_name,

                        cl.arabic_name as class

                    FROM students s

                    LEFT JOIN churches c ON s.church_id = c.id

                    LEFT JOIN classes cl ON s.class_id = cl.id

                    WHERE s.phone LIKE CONCAT('%', ?)

                       OR REPLACE(s.phone, '''', '') LIKE CONCAT('%', ?)

                ");



                $stmt2->bind_param("ss", $last9Digits, $last9Digits);

                $stmt2->execute();

                $result2 = $stmt2->get_result();



                $students2 = [];

                while ($row = $result2->fetch_assoc()) {

                    $row['birthday'] = formatDateFromDB($row['birthday']);

                    $row['class'] = $row['class'];

                    $students2[] = $row;

                }



                if (count($students2) > 0) {

                    sendJSON([

                        'success' => true,

                        'data' => $students2,

                        'message' => 'تم العثور على ' . count($students2) . ' طفل'

                    ]);

                    return;

                }

            }



            sendJSON([

                'success' => false,

                'message' => 'لم يتم العثور على طفل بهذا الرقم',

                'data' => []

            ]);

        }



    } catch (Exception $e) {

        error_log("getStudentByPhone error: " . $e->getMessage());

        error_log("Stack trace: " . $e->getTraceAsString());

        sendJSON(['success' => false, 'message' => 'خطأ في البحث: ' . $e->getMessage()]);

    }

}

function getStudentAttendance()

{

    try {

        $studentId = intval($_POST['studentId'] ?? $_GET['studentId'] ?? 0);



        if ($studentId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف الطفل مطلوب']);

        }



        $conn = getDBConnection();



        $stmt = $conn->prepare("

            SELECT attendance_date, status

            FROM attendance

            WHERE student_id = ?

            ORDER BY attendance_date DESC

        ");



        $stmt->bind_param("i", $studentId);

        $stmt->execute();

        $result = $stmt->get_result();



        $attendance = [];

        while ($row = $result->fetch_assoc()) {

            $attendance[] = [

                'attendance_date' => $row['attendance_date'],

                'status' => $row['status']

            ];

        }



        sendJSON([

            'success' => true,

            'attendance' => $attendance,

            'count' => count($attendance)

        ]);



    } catch (Exception $e) {

        error_log("getStudentAttendance error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في جلب الحضور']);

    }

}

function approveRegistration()

{

    try {

        $churchId = getChurchId();

        $registrationId = intval($_POST['registrationId'] ?? 0);



        if ($registrationId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف التسجيل مطلوب']);

            return;

        }



        error_log("======================================");

        error_log("بدء الموافقة على تسجيل ID: $registrationId للكنيسة ID: $churchId");



        $regData = debugRegistrationData($registrationId);



        if (!$regData) {

            sendJSON(['success' => false, 'message' => 'لم يتم العثور على التسجيل']);

            return;

        }



        $conn = getDBConnection();

        $conn->begin_transaction();



        $name = $regData['name'];

        $className = trim($regData['class']);

        $address = $regData['address'] ?? '';

        $phone = $regData['phone'] ?? '';

        $birthday = $regData['birthday'] ?? '';

        $imageUrl = $regData['image_url'] ?? null;

        $passwordHash = $regData['password_hash'] ?? null;

        $username = $regData['username'] ?? null;

        $customInfo = !empty($username) ? json_encode(['username' => $username], JSON_UNESCAPED_UNICODE) : null;



        error_log("البيانات: الاسم='$name', الفصل='$className'");

        error_log("الفصل (hex): " . bin2hex($className));



        // ── 1. Try church_classes first (custom) ──────────────────

        $classId = null;

        $finalClassName = null;



        $customStmt = $conn->prepare("

            SELECT id, arabic_name 

            FROM church_classes 

            WHERE church_id = ? AND arabic_name = ? AND is_active = 1

        ");

        $customStmt->bind_param("is", $churchId, $className);

        $customStmt->execute();

        $customResult = $customStmt->get_result();



        if ($row = $customResult->fetch_assoc()) {

            $classId = $row['id'];

            $finalClassName = $row['arabic_name'];

            error_log("✅ وُجد الفصل في church_classes: $finalClassName (ID: $classId)");

        }



        // ── 2. Fall back to global classes table ──────────────────

        if (!$classId) {

            $globalStmt = $conn->prepare("

                SELECT id, arabic_name FROM classes WHERE arabic_name = ?

            ");

            $globalStmt->bind_param("s", $className);

            $globalStmt->execute();

            $globalResult = $globalStmt->get_result();



            if ($row = $globalResult->fetch_assoc()) {

                $classId = $row['id'];

                $finalClassName = $row['arabic_name'];

                error_log("✅ وُجد الفصل في classes: $finalClassName (ID: $classId)");

            }

        }



        // ── 3. Try cleaned / partial match ───────────────────────

        if (!$classId) {

            $cleanedClass = trim(preg_replace('/[^\p{Arabic}\s]/u', '', $className));

            error_log("⚠️ محاولة بحث جزئي بعد التنظيف: '$cleanedClass'");



            // Search custom first

            $fuzzyCustom = $conn->prepare("

                SELECT id, arabic_name FROM church_classes 

                WHERE church_id = ? AND arabic_name LIKE ? AND is_active = 1

                LIMIT 1

            ");

            $like = "%$cleanedClass%";

            $fuzzyCustom->bind_param("is", $churchId, $like);

            $fuzzyCustom->execute();



            if ($row = $fuzzyCustom->get_result()->fetch_assoc()) {

                $classId = $row['id'];

                $finalClassName = $row['arabic_name'];

                error_log("✅ وُجد الفصل (جزئي-مخصص): $finalClassName (ID: $classId)");

            } else {

                // Search global

                $fuzzyGlobal = $conn->prepare("

                    SELECT id, arabic_name FROM classes 

                    WHERE arabic_name LIKE ? LIMIT 1

                ");

                $fuzzyGlobal->bind_param("s", $like);

                $fuzzyGlobal->execute();



                if ($row = $fuzzyGlobal->get_result()->fetch_assoc()) {

                    $classId = $row['id'];

                    $finalClassName = $row['arabic_name'];

                    error_log("✅ وُجد الفصل (جزئي-افتراضي): $finalClassName (ID: $classId)");

                }

            }

        }



        // ── 4. Absolute fallback — first class for this church ────

        if (!$classId) {

            error_log("❌ لم يُوجد الفصل '$className' — استخدام أول فصل متاح");

            $allClasses = getClassesForChurch($churchId);

            if (!empty($allClasses)) {

                $classId = $allClasses[0]['id'];

                $finalClassName = $allClasses[0]['arabic_name'];

                error_log("⚠️ الفصل الاحتياطي: $finalClassName (ID: $classId)");

            } else {

                $conn->rollback();

                sendJSON(['success' => false, 'message' => 'لا توجد فصول متاحة لهذه الكنيسة']);

                return;

            }

        }



        error_log("البيانات النهائية: Class=$finalClassName, ID=$classId");



        $gender = $regData['gender'] ?? '';

        if ($gender !== 'male' && $gender !== 'female') {

            $gender = detectGenderFromName($name);

        }



        // ── Insert student ────────────────────────────────────────

        $insertStmt = $conn->prepare("

            INSERT INTO students

            (church_id, name, class_id, class, address, phone, birthday,

             image_url, password_hash, custom_info, gender,

             commitment_coupons, coupons, attendance_coupons, created_at)

            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, NOW())

        ");

        $insertStmt->bind_param(

            "issssssssss",

            $churchId,

            $name,

            $classId,

            $finalClassName,

            $address,

            $phone,

            $birthday,

            $imageUrl,

            $passwordHash,

            $customInfo,

            $gender

        );



        if (!$insertStmt->execute()) {

            $conn->rollback();

            error_log("❌ فشل في إضافة الطفل: " . $insertStmt->error);

            sendJSON(['success' => false, 'message' => 'فشل في إضافة الطفل: ' . $insertStmt->error]);

            return;

        }



        $newStudentId = $conn->insert_id;

        error_log("✅ تم إضافة الطفل ID: $newStudentId في فصل: $finalClassName");



        // ── Update registration status ────────────────────────────

        $updateStmt = $conn->prepare("

            UPDATE pending_registrations 

            SET status = 'approved', updated_at = NOW() 

            WHERE id = ?

        ");

        $updateStmt->bind_param("i", $registrationId);



        if (!$updateStmt->execute()) {

            $conn->rollback();

            error_log("❌ فشل في تحديث حالة التسجيل: " . $updateStmt->error);

            sendJSON(['success' => false, 'message' => 'فشل في تحديث حالة التسجيل']);

            return;

        }



        $conn->commit();



        // ► AUDIT

        auditRegistrationDecision($registrationId, $name, 'approved');



        sendJSON([

            'success' => true,

            'message' => 'تمت الموافقة وإضافة الطفل في فصل ' . $finalClassName,

            'student_id' => $newStudentId,

            'class_name' => $finalClassName,

        ]);



    } catch (Exception $e) {

        if (isset($conn))

            $conn->rollback();

        error_log("❌ approveRegistration error: " . $e->getMessage());

        error_log($e->getTraceAsString());

        sendJSON(['success' => false, 'message' => 'خطأ في الموافقة: ' . $e->getMessage()]);

    }

}

function debugRegistrationData($registrationId)

{

    try {

        $conn = getDBConnection();



        error_log("========== DEBUG REGISTRATION DATA ==========");



        // 1. التحقق من جدول classes

        $classes = $conn->query("SELECT id, code, arabic_name FROM classes ORDER BY id");

        error_log("📋 الفصول المتاحة في قاعدة البيانات:");

        while ($class = $classes->fetch_assoc()) {

            error_log("  ID: {$class['id']}, Code: '{$class['code']}', Arabic: '{$class['arabic_name']}'");

        }



        // 2. جلب بيانات التسجيل المحدد

        $stmt = $conn->prepare("SELECT * FROM pending_registrations WHERE id = ?");

        $stmt->bind_param("i", $registrationId);

        $stmt->execute();

        $result = $stmt->get_result();



        if ($row = $result->fetch_assoc()) {

            error_log("📝 بيانات التسجيل (ID: $registrationId):");

            error_log("  الاسم: " . ($row['name'] ?? 'NULL'));

            error_log("  الفصل (raw): '" . ($row['class'] ?? 'NULL') . "'");

            error_log("  الهاتف: " . ($row['phone'] ?? 'NULL'));

            error_log("  البريد: " . ($row['email'] ?? 'NULL'));



            // 3. عرض القيمة بالـ HEX للكشف عن الأحرف المخفية

            $classValue = $row['class'] ?? '';

            error_log("  الفصل (hex): " . bin2hex($classValue));



            return $row;

        } else {

            error_log("❌ لم يتم العثور على تسجيل ID: $registrationId");

        }



        error_log("==============================================");

        return null;



    } catch (Exception $e) {

        error_log("Debug error: " . $e->getMessage());

        return null;

    }

}

function ensureClassesTable()

{

    try {

        $conn = getDBConnection();



        // التحقق من وجود جدول classes

        $tableCheck = $conn->query("SHOW TABLES LIKE 'classes'");

        if (!$tableCheck || $tableCheck->num_rows === 0) {

            // إنشاء جدول classes إذا لم يكن موجوداً

            $createTable = "

                CREATE TABLE IF NOT EXISTS classes (

                    id INT AUTO_INCREMENT PRIMARY KEY,

                    code VARCHAR(10) NOT NULL UNIQUE,

                    arabic_name VARCHAR(50) NOT NULL,

                    display_order INT DEFAULT 0

                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci

            ";

            $conn->query($createTable);



            // إدخال بيانات الفصول الأساسية

            $insertClasses = "

                INSERT INTO classes (code, arabic_name, display_order) VALUES

                ('nursery', 'حضانة', 1),

                ('grade1', 'أولى', 2),

                ('grade2', 'تانية', 3),

                ('grade3', 'تالتة', 4),

                ('grade4', 'رابعة', 5),

                ('grade5', 'خامسة', 6),

                ('grade6', 'سادسة', 7)

                ON DUPLICATE KEY UPDATE arabic_name = VALUES(arabic_name), display_order = VALUES(display_order)

            ";

            $conn->query($insertClasses);



            error_log("✅ تم إنشاء جدول classes وإضافة البيانات الأساسية");

        }



        // التحقق من وجود البيانات

        $checkData = $conn->query("SELECT COUNT(*) as count FROM classes");

        $data = $checkData->fetch_assoc();



        if ($data['count'] == 0) {

            // إدخال البيانات إذا كان الجدول فارغاً

            $insertClasses = "

                INSERT INTO classes (code, arabic_name, display_order) VALUES

                ('nursery', 'حضانة', 1),

                ('grade1', 'أولى', 2),

                ('grade2', 'تانية', 3),

                ('grade3', 'تالتة', 4),

                ('grade4', 'رابعة', 5),

                ('grade5', 'خامسة', 6),

                ('grade6', 'سادسة', 7)

            ";

            $conn->query($insertClasses);

            error_log("✅ تم إضافة بيانات الفصول إلى جدول classes");

        }



    } catch (Exception $e) {

        error_log("ensureClassesTable error: " . $e->getMessage());

    }

}



// ===== VERIFY CHURCH GATE PASSWORD =====

function verifyChurchPassword()

{

    try {

        $churchCode = sanitize($_POST['church_code'] ?? '');

        $password = $_POST['password'] ?? '';

        if (empty($churchCode) || empty($password)) {

            sendJSON(['success' => false, 'message' => 'البيانات ناقصة']);

            return;

        }

        $conn = getDBConnection();

        $passwordHash = hash('sha256', $password);

        $stmt = $conn->prepare(

            "SELECT id FROM churches WHERE church_code = ? AND password_hash = ? LIMIT 1"

        );

        $stmt->bind_param("ss", $churchCode, $passwordHash);

        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();

        if ($row) {

            sendJSON(['success' => true]);

        } else {

            sendJSON(['success' => false, 'message' => 'كلمة السر غير صحيحة']);

        }

    } catch (Exception $e) {

        error_log("verifyChurchPassword error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في التحقق']);

    }

}

// ===== AUTO LOGIN FUNCTION =====

function handleAutoLogin()

{

    try {

        $churchCode = sanitize($_POST['church_code'] ?? '');

        $churchIdDirect = intval($_POST['church_id'] ?? 0);



        if (empty($churchCode) && $churchIdDirect === 0) {

            sendJSON(['success' => false, 'message' => 'رمز الكنيسة مطلوب']);

        }



        $conn = getDBConnection();



        // Ensure church_type column exists

        ensureChurchTypeColumn($conn);



        if (!empty($churchCode)) {

            $stmt = $conn->prepare("SELECT id, church_name, church_code, COALESCE(church_type,'kids') AS church_type FROM churches WHERE church_code = ? LIMIT 1");

            $stmt->bind_param("s", $churchCode);

        } else {

            $stmt = $conn->prepare("SELECT id, church_name, church_code, COALESCE(church_type,'kids') AS church_type FROM churches WHERE id = ? LIMIT 1");

            $stmt->bind_param("i", $churchIdDirect);

        }

        $stmt->execute();

        $result = $stmt->get_result();



        if ($row = $result->fetch_assoc()) {

            session_regenerate_id(true);



            $_SESSION['church_id'] = $row['id'];

            $_SESSION['church_name'] = $row['church_name'];

            $_SESSION['church_code'] = $row['church_code'];

            $_SESSION['auto_logged_in'] = true;

            runBackgroundGradeUpChecks();



            sendJSON([

                'success' => true,

                'message' => 'تم تسجيل الدخول تلقائياً',

                'church_name' => $row['church_name'],

                'church_id' => $row['id'],

                'church_type' => $row['church_type'],

            ]);

        } else {

            sendJSON(['success' => false, 'message' => 'رمز الكنيسة غير صحيح']);

        }



    } catch (Exception $e) {

        error_log("Auto-login error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تسجيل الدخول التلقائي']);

    }

}

function updateRegistration()

{

    try {

        $churchId = getChurchId();

        $registrationId = intval($_POST['registrationId'] ?? 0);

        $status = sanitize($_POST['status'] ?? '');

        $rejectionNote = sanitize($_POST['rejectionNote'] ?? '');



        if ($registrationId === 0 || empty($status)) {

            sendJSON(['success' => false, 'message' => 'بيانات غير كاملة']);

            return;

        }



        // If this is a single approval, just call approveRegistration

        if ($status === 'approved') {

            // Get the registration data

            $conn = getDBConnection();

            $stmt = $conn->prepare("SELECT class FROM pending_registrations WHERE id = ? AND church_id = ?");

            $stmt->bind_param("ii", $registrationId, $churchId);

            $stmt->execute();

            $result = $stmt->get_result();



            if ($row = $result->fetch_assoc()) {

                $_POST['registrationId'] = $registrationId;

                // Reuse the approveRegistration function

                approveRegistration();

                return;

            }

        }



        $conn = getDBConnection();

        $conn->begin_transaction();



        $tables = ['pending_registrations', 'registrations'];

        $updated = false;



        foreach ($tables as $table) {

            $checkTable = $conn->query("SHOW TABLES LIKE '$table'");

            if (!$checkTable || $checkTable->num_rows === 0) {

                continue;

            }



            // Get registration data

            $stmt = $conn->prepare("

                SELECT * FROM $table 

                WHERE id = ? AND church_id = ?

            ");

            $stmt->bind_param("ii", $registrationId, $churchId);

            $stmt->execute();

            $result = $stmt->get_result();

            $registration = $result->fetch_assoc();



            if (!$registration) {

                continue;

            }



            // Extract user data

            $userEmail = $registration['email'];

            $userName = $registration['name'];

            $userPhone = $registration['phone'];

            $studentClass = $registration['class'];



            if ($status === 'approved') {

                // Add child to students table

                $name = $registration['name'];

                $class = $registration['class'];

                $address = $registration['address'] ?? '';

                $phone = $registration['phone'] ?? '';

                $birthday = $registration['birthday'] ?? '';



                if ($address === null)

                    $address = '';

                if ($phone === null)

                    $phone = '';

                if ($birthday === null)

                    $birthday = '';



                // Get class_id and class name from classes table

                // Look up class by Arabic name — check church custom classes first, then global

                $classId = null;

                $className = null;



                // 1. Check church_classes

                $ccStmt = $conn->prepare("

    SELECT id, arabic_name FROM church_classes 

    WHERE church_id = ? AND arabic_name = ? AND is_active = 1 LIMIT 1

");

                $ccStmt->bind_param("is", $churchId, $class);

                $ccStmt->execute();

                if ($ccRow = $ccStmt->get_result()->fetch_assoc()) {

                    $classId = $ccRow['id'];

                    $className = $ccRow['arabic_name'];

                }



                // 2. Fall back to global classes

                if (!$classId) {

                    $gcStmt = $conn->prepare("SELECT id, arabic_name FROM classes WHERE arabic_name = ? LIMIT 1");

                    $gcStmt->bind_param("s", $class);

                    $gcStmt->execute();

                    if ($gcRow = $gcStmt->get_result()->fetch_assoc()) {

                        $classId = $gcRow['id'];

                        $className = $gcRow['arabic_name'];

                    }

                }



                // 3. Still not found — report error and skip

                if (!$classId) {

                    $errors[] = "السطر $lineNumber: الفصل '$class' غير موجود";

                    $errorCount++;

                    continue;

                }



                $addStmt = $conn->prepare("

                    INSERT INTO students 

                    (church_id, name, class_id, class, address, phone, birthday, commitment_coupons, coupons, attendance_coupons)

                    VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 0)

                ");

                $addStmt->bind_param(

                    "issssss",

                    $churchId,

                    $name,

                    $classId,

                    $className,

                    $address,

                    $phone,

                    $birthday

                );



                if (!$addStmt->execute()) {

                    throw new Exception("فشل في إضافة الطفل: " . $addStmt->error);

                }

            }



            // Update registration status

            $updateStmt = $conn->prepare("

                UPDATE $table 

                SET status = ?, updated_at = NOW(), rejection_note = ?

                WHERE id = ?

            ");

            $updateStmt->bind_param("ssi", $status, $rejectionNote, $registrationId);



            if ($updateStmt->execute()) {

                $updated = true;



                // Send email notification

                sendRegistrationStatusEmail($churchId, $status, [

                    'user_email' => $userEmail,

                    'user_name' => $userName,

                    'user_phone' => $userPhone,

                    'class' => $studentClass,

                    'registration_id' => $registrationId,

                    'rejection_note' => $rejectionNote

                ]);



                break;

            }

        }



        if ($updated) {

            $conn->commit();

            sendJSON([

                'success' => true,

                'message' => 'تم تحديث حالة الطلب بنجاح',

                'registrationId' => $registrationId,

                'status' => $status

            ]);

        } else {

            $conn->rollback();

            sendJSON(['success' => false, 'message' => 'لم يتم العثور على الطلب']);

        }



    } catch (Exception $e) {

        error_log("updateRegistration error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحديث الطلب: ' . $e->getMessage()]);

    }

}



function sendRegistrationStatusEmail($churchId, $status, $data)

{

    try {

        $churchName = getChurchName($churchId);

        $userEmail = $data['user_email'];

        $userName = $data['user_name'];



        if (empty($userEmail)) {

            error_log("No email provided for registration status notification");

            return false;

        }



        $googleScriptUrl = 'https://script.google.com/macros/s/AKfycbxsDA0veJTA3C_2Bw47coffOagRigWwaZnyxWuGb_gSVUCWM958V1bUcaZDwfIHVZ7b1g/exec';



        if ($status === 'approved') {

            // إرسال رسالة القبول

            $emailData = [

                'action' => 'sendApprovalEmail',

                'user_email' => $userEmail,

                'user_name' => $userName,

                'church_name' => $churchName,

                'class' => $data['class'],

                'registration_id' => $data['registration_id']

            ];

        } else {

            // إرسال رسالة الرفض

            $emailData = [

                'action' => 'sendRejectionEmail',

                'user_email' => $userEmail,

                'user_name' => $userName,

                'church_name' => $churchName,

                'rejection_note' => $data['rejection_note'] ?? 'لم يتم تحديد سبب محدد',

                'registration_id' => $data['registration_id']

            ];

        }



        sendAsyncRequest($googleScriptUrl, $emailData);

        return true;



    } catch (Exception $e) {

        error_log("Error in sendRegistrationStatusEmail: " . $e->getMessage());

        return false;

    }

}

function bulkUpdateRegistrations()

{

    try {

        $churchId = getChurchId();

        $registrationIds = json_decode($_POST['registrationIds'] ?? '[]', true);

        $status = sanitize($_POST['status'] ?? '');



        if (empty($registrationIds) || empty($status)) {

            sendJSON(['success' => false, 'message' => 'بيانات غير كاملة']);

            return;

        }



        $conn = getDBConnection();

        $conn->begin_transaction();



        $idsString = implode(',', array_map('intval', $registrationIds));

        $successCount = 0;



        $tables = ['pending_registrations', 'registrations'];



        foreach ($tables as $table) {

            $checkTable = $conn->query("SHOW TABLES LIKE '$table'");

            if (!$checkTable || $checkTable->num_rows === 0) {

                continue;

            }



            if ($status === 'approved') {

                $getStmt = $conn->prepare("

                    SELECT * FROM $table 

                    WHERE id IN ($idsString) AND church_id = ? AND status = 'pending'

                ");

                $getStmt->bind_param("i", $churchId);

                $getStmt->execute();

                $result = $getStmt->get_result();



                while ($registration = $result->fetch_assoc()) {

                    // Extract values

                    $name = $registration['name'];

                    $class = $registration['class'];

                    $address = $registration['address'];

                    $phone = $registration['phone'];

                    $birthday = $registration['birthday'];



                    // Ensure empty strings instead of NULL

                    if ($address === null)

                        $address = '';

                    if ($phone === null)

                        $phone = '';

                    if ($birthday === null)

                        $birthday = '';



                    // Get class_id and class name from classes table

                    $classData = getClassByCode($class);

                    if (!$classData) {

                        // If class not found in classes table, use default

                        $classId = 1;

                        $className = 'حضانة';

                        error_log("Class '$class' not found for bulk approval, using default ID: $classId");

                    } else {

                        $classId = $classData['id'];

                        $className = $classData['arabic_name'] ?? $class;

                    }



                    $addStmt = $conn->prepare("

                        INSERT INTO students 

                        (church_id, name, class_id, class, address, phone, birthday, commitment_coupons, coupons, attendance_coupons)

                        VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 0)

                    ");

                    $addStmt->bind_param(

                        "issssss",

                        $churchId,

                        $name,

                        $classId,

                        $className,

                        $address,

                        $phone,

                        $birthday

                    );



                    if ($addStmt->execute()) {

                        $successCount++;

                    } else {

                        error_log("Failed to add student in bulk: " . $addStmt->error);

                    }

                }

            }



            $updateStmt = $conn->prepare("

                UPDATE $table 

                SET status = ?, updated_at = NOW() 

                WHERE id IN ($idsString) AND church_id = ?

            ");

            $updateStmt->bind_param("si", $status, $churchId);



            if ($updateStmt->execute()) {

                $conn->commit();

                sendJSON([

                    'success' => true,

                    'message' => 'تم تحديث ' . count($registrationIds) . ' طلب بنجاح',

                    'updatedCount' => count($registrationIds),

                    'approvedCount' => $successCount

                ]);

                return;

            }

        }



        $conn->rollback();

        sendJSON(['success' => false, 'message' => 'فشل في تحديث الطلبات']);



    } catch (Exception $e) {

        if (isset($conn))

            $conn->rollback();

        error_log("bulkUpdateRegistrations error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحديث الطلبات: ' . $e->getMessage()]);

    }

}

function syncClassNames($pdo)

{

    try {

        $sql = "

            UPDATE students s

            JOIN classes c ON s.class_id = c.id

            SET s.class = c.arabic_name

            WHERE s.class IS NULL 

               OR s.class != c.arabic_name

        ";



        $stmt = $pdo->prepare($sql);

        $stmt->execute();



        $affectedRows = $stmt->rowCount();

        error_log("Synced class names for $affectedRows students");



        return $affectedRows;



    } catch (Exception $e) {

        error_log("syncClassNames error: " . $e->getMessage());

        return 0;

    }

}



// ===== FIXED: GET PENDING REGISTRATIONS =====

function getPendingRegistrations()

{

    try {

        $churchId = getChurchId();

        $class = sanitize($_POST['class'] ?? $_GET['class'] ?? '');



        error_log("Getting pending registrations for church: $churchId, class: $class");



        $conn = getDBConnection();



        // Check if table exists

        $tableName = 'pending_registrations';

        $tableExists = $conn->query("SHOW TABLES LIKE '$tableName'");



        if (!$tableExists || $tableExists->num_rows === 0) {

            sendJSON([

                'success' => false,

                'data' => [],

                'message' => 'جدول التسجيلات المعلقة غير موجود'

            ]);

            return;

        }



        // Build query - تم إزالة التعليقات من SQL

        $sql = "SELECT 

                    id,

                    name as 'الاسم',

                    class as 'الفصل',

                    birthday as birthday_db,

                    phone as 'الهاتف',

                    email as 'البريد الإلكتروني',

                    address as 'العنوان',

                    status as 'الحالة',

                    rejection_note as 'ملاحظة الرفض',

                    DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as 'تاريخ الإنشاء',

                    DATE_FORMAT(updated_at, '%d/%m/%Y %H:%i') as 'تاريخ التحديث',

                    COALESCE(username, '') as username,

                    COALESCE(image_url, '') as image_url

                FROM pending_registrations 

                WHERE church_id = ? AND status = 'pending'";



        $params = [$churchId];

        $types = "i";



        if (!empty($class) && $class !== 'الجميع' && $class !== 'جميع') {

            $sql .= " AND class = ?";

            $params[] = $class;

            $types .= "s";

        }



        $sql .= " ORDER BY created_at DESC";



        error_log("SQL Query: " . $sql);

        error_log("Parameters: " . print_r($params, true));



        $stmt = $conn->prepare($sql);



        if (!$stmt) {

            throw new Exception("خطأ في إعداد الاستعلام: " . $conn->error);

        }



        if (count($params) > 0) {

            $stmt->bind_param($types, ...$params);

        }



        $stmt->execute();

        $result = $stmt->get_result();



        $registrations = [];

        while ($row = $result->fetch_assoc()) {

            // تنسيق تاريخ الميلاد

            $row['تاريخ الميلاد'] = formatDateFromDB($row['birthday_db']);



            // إزالة الحقل الخام

            unset($row['birthday_db']);



            // إذا كان هناك ملاحظة رفض فارغة، اجعلها نصاً فارغاً

            if (isset($row['ملاحظة الرفض']) && empty($row['ملاحظة الرفض'])) {

                $row['ملاحظة الرفض'] = '';

            }



            $registrations[] = $row;

        }



        error_log("Found " . count($registrations) . " pending registrations");



        sendJSON([

            'success' => true,

            'data' => $registrations,

            'count' => count($registrations),

            'message' => count($registrations) > 0 ?

                'تم العثور على ' . count($registrations) . ' طلب معلق' :

                'لا توجد طلبات معلقة'

        ]);



    } catch (Exception $e) {

        error_log("getPendingRegistrations error: " . $e->getMessage());

        error_log("Stack trace: " . $e->getTraceAsString());

        sendJSON([

            'success' => false,

            'data' => [],

            'message' => 'خطأ في جلب الطلبات المعلقة: ' . $e->getMessage()

        ]);

    }

}

function rejectRegistration()

{

    try {

        $churchId = getChurchId();

        $registrationId = intval($_POST['registrationId'] ?? 0);



        if ($registrationId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف التسجيل مطلوب']);

        }



        $conn = getDBConnection();

        $stmt = $conn->prepare("

            UPDATE pending_registrations 

            SET status = 'rejected', updated_at = NOW() 

            WHERE id = ? AND church_id = ?

        ");

        $stmt->bind_param("ii", $registrationId, $churchId);



        if ($stmt->execute()) {

            // ► AUDIT

            auditRegistrationDecision($registrationId, '', 'rejected');



            sendJSON(['success' => true, 'message' => 'تم رفض التسجيل']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في رفض التسجيل']);

        }



    } catch (Exception $e) {

        error_log("rejectRegistration error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في رفض التسجيل']);

    }

}

function getAnnouncementsForStudent()

{

    try {

        $churchId = intval($_POST['churchId'] ?? $_GET['churchId'] ?? 0);

        $studentClass = sanitize($_POST['studentClass'] ?? $_GET['studentClass'] ?? '');

        $studentName = sanitize($_POST['studentName'] ?? $_GET['studentName'] ?? '');



        if ($churchId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف الكنيسة مطلوب']);

        }



        $conn = getDBConnection();



        // Set Cairo timezone

        $conn->query("SET time_zone = '+02:00'");



        $stmt = $conn->prepare("

            SELECT 

                id, 

                type, 

                text, 

                link, 

                class, 

                student_names,

                DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', '+02:00'), '%d/%m/%Y %h:%i %p') as created_at

            FROM announcements

            WHERE church_id = ?

            AND is_active = 1

            AND (

                class = 'الجميع' 

                OR class = ?

                OR FIND_IN_SET(?, class) > 0

                OR (

                    student_names IS NOT NULL 

                    AND student_names != ''

                    AND (

                        student_names LIKE CONCAT('%', ?, '%')

                        OR student_names = ?

                    )

                )

            )

            ORDER BY created_at DESC

        ");



        $stmt->bind_param("issss", $churchId, $studentClass, $studentClass, $studentName, $studentName);

        $stmt->execute();

        $result = $stmt->get_result();



        $announcements = [];

        while ($row = $result->fetch_assoc()) {

            // Replace AM/PM with Arabic

            if (!empty($row['created_at'])) {

                $row['created_at'] = str_replace(['AM', 'PM'], ['صباحاً', 'مساءً'], $row['created_at']);

            }

            $announcements[] = [

                'id' => $row['id'],

                'type' => $row['type'],

                'text' => $row['text'],

                'link' => $row['link'],

                'class' => $row['class'],

                'student_names' => $row['student_names'],

                'created_at' => $row['created_at']

            ];

        }



        sendJSON([

            'success' => true,

            'announcements' => $announcements,

            'count' => count($announcements)

        ]);



    } catch (Exception $e) {

        error_log("getAnnouncementsForStudent error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في جلب الإعلانات']);

    }

}



function getPublicStats()

{

    try {

        $conn = getDBConnection();



        $kids_count = $conn->query("SELECT COUNT(*) as cnt FROM students")->fetch_assoc()['cnt'] ?? 0;

        $servants_count = $conn->query("SELECT COUNT(*) as cnt FROM uncles WHERE deleted = 0")->fetch_assoc()['cnt'] ?? 0;

        $churches_count = $conn->query("SELECT COUNT(*) as cnt FROM churches WHERE admin_email IS NOT NULL AND admin_email != ''")->fetch_assoc()['cnt'] ?? 0;



        sendJSON([

            'success' => true,

            'kids_count' => (int) $kids_count,

            'servants_count' => (int) $servants_count,

            'churches_count' => (int) $churches_count,

        ]);

    } catch (Exception $e) {

        error_log("getPublicStats error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في جلب الإحصائيات']);

    }

}



function getAllChurches()

{

    try {

        $conn = getDBConnection();



        $stmt = $conn->prepare("

            SELECT id, church_name, church_code, admin_email

            FROM churches 

            WHERE admin_email IS NOT NULL 

            AND admin_email != ''

            ORDER BY church_name

        ");

        $stmt->execute();

        $result = $stmt->get_result();



        $churches = [];

        while ($row = $result->fetch_assoc()) {

            $churches[] = [

                'id' => $row['id'],

                'name' => $row['church_name'],

                'code' => $row['church_code'],

                'email' => $row['admin_email']

            ];

        }



        sendJSON([

            'success' => true,

            'churches' => $churches

        ]);



    } catch (Exception $e) {

        error_log("getAllChurches error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في جلب قائمة الكنائس']);

    }

}

// ===== ADMIN: ADD CHURCH =====

function addChurch()

{

    checkAuth();



    try {

        $role = $_SESSION['uncle_role'] ?? 'uncle';



        // Only developers can add churches

        if ($role !== 'developer') {

            sendJSON(['success' => false, 'message' => 'غير مصرح - فقط للمطورين']);

        }



        $churchName = sanitize($_POST['church_name'] ?? '');

        $churchCode = sanitize($_POST['church_code'] ?? '');

        $password = $_POST['church_password'] ?? '';

        // church_type: 'kids' (default) or 'youth'

        $churchType = in_array($_POST['church_type'] ?? '', ['kids', 'youth']) ? $_POST['church_type'] : 'kids';



        if (empty($churchName) || empty($churchCode) || empty($password)) {

            sendJSON(['success' => false, 'message' => 'جميع الحقول مطلوبة']);

        }



        // Validate church code format (only lowercase letters, numbers, underscores)

        if (!preg_match('/^[a-z0-9_]+$/', $churchCode)) {

            sendJSON(['success' => false, 'message' => 'رمز الكنيسة يجب أن يحتوي على أحرف صغيرة وأرقام وشرطة سفلية فقط']);

        }



        $passwordHash = hash('sha256', $password);



        $conn = getDBConnection();



        // Ensure church_type column exists (safe for both DBs)

        // ALTER TABLE churches ADD COLUMN IF NOT EXISTS church_type ENUM('kids','youth') NOT NULL DEFAULT 'kids';

        ensureChurchTypeColumn($conn);



        // Check if church code already exists

        $checkStmt = $conn->prepare("SELECT id FROM churches WHERE church_code = ?");

        $checkStmt->bind_param("s", $churchCode);

        $checkStmt->execute();

        if ($checkStmt->get_result()->num_rows > 0) {

            sendJSON(['success' => false, 'message' => 'رمز الكنيسة موجود بالفعل']);

        }



        $stmt = $conn->prepare("INSERT INTO churches (church_name, church_code, password_hash, church_type) VALUES (?, ?, ?, ?)");

        $stmt->bind_param("ssss", $churchName, $churchCode, $passwordHash, $churchType);



        if ($stmt->execute()) {

            $newChurchId = $conn->insert_id;



            // ── Seed default classes based on type ─────────────────

            if ($churchType === 'youth') {

                $youthClasses = [

                    ['youth_prep', 'إعدادي', 1, '#4f46e5'],

                    ['youth_sec', 'ثانوي', 2, '#10b981'],

                    ['youth_uni', 'جامعة', 3, '#f59e0b'],

                    ['youth_grad', 'خريجين', 4, '#8b5cf6'],

                ];

                $clsStmt = $conn->prepare(

                    "INSERT INTO church_classes (church_id, code, arabic_name, display_order, color, is_active) VALUES (?, ?, ?, ?, ?, 1)"

                );

                foreach ($youthClasses as [$code, $name, $order, $color]) {

                    $clsStmt->bind_param("issis", $newChurchId, $code, $name, $order, $color);

                    $clsStmt->execute();

                }

            }

            // kids churches keep the global default classes table



            sendJSON(['success' => true, 'message' => 'تم إضافة الكنيسة بنجاح', 'church_type' => $churchType]);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في إضافة الكنيسة: ' . $conn->error]);

        }



    } catch (Exception $e) {

        error_log("addChurch error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في إضافة الكنيسة']);

    }

}



// ===== ADMIN: GET ALL CHURCHES (FOR ADMIN) =====

function getAllChurchesForAdmin()

{

    checkAuth();



    try {

        $role = $_SESSION['uncle_role'] ?? 'uncle';



        if ($role !== 'developer') {

            sendJSON(['success' => false, 'message' => 'غير مصرح']);

        }



        $churches = [];



        // Query helper — runs against one connection and appends to $churches

        $fetchFrom = function (mysqli $conn, string $dbLabel) use (&$churches) {

            // Ensure church_type column exists on this DB

            ensureChurchTypeColumn($conn);



            $stmt = $conn->prepare("

                SELECT 

                    c.id, c.church_name, c.church_code, c.admin_email, c.created_at,

                    COALESCE(c.church_type, 'kids') AS church_type,

                    (SELECT COUNT(*) FROM students WHERE church_id = c.id) as student_count,

                    (SELECT COUNT(*) FROM uncles WHERE church_id = c.id AND deleted = 0) as uncle_count

                FROM churches c

                ORDER BY c.created_at DESC

            ");

            if (!$stmt)

                return;

            $stmt->execute();

            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach ($rows as $row) {

                $row['_db'] = $dbLabel; // tag which DB it came from

                $churches[] = $row;

            }

        };



        // Single database

        $fetchFrom(getDBConnection(), 'kids');



        // Sort combined list by created_at DESC

        usort($churches, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));



        sendJSON(['success' => true, 'churches' => $churches]);



    } catch (Exception $e) {

        error_log("getAllChurchesForAdmin error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في جلب الكنائس']);

    }

}



// ===== ADMIN: UPDATE CHURCH =====

function updateChurch()

{

    checkAuth();



    try {

        $role = $_SESSION['uncle_role'] ?? 'uncle';

        $churchId = intval($_POST['church_id'] ?? 0);



        $isDeveloper = in_array(strtolower($role), ['developer', 'dev']);

        $isChurchAdmin = ($role === 'admin' || (isset($_SESSION['church_id']) && intval($_SESSION['church_id']) === $churchId));



        if (!$isDeveloper && !$isChurchAdmin) {

            sendJSON(['success' => false, 'message' => 'غير مصرح']);

            return;

        }

        $churchName = sanitize($_POST['church_name'] ?? '');

        $adminEmail = sanitize($_POST['admin_email'] ?? '');

        $churchType = in_array($_POST['church_type'] ?? '', ['kids', 'youth']) ? $_POST['church_type'] : 'kids';



        if ($churchId === 0 || empty($churchName)) {

            sendJSON(['success' => false, 'message' => 'بيانات غير كاملة']);

        }



        $conn = getDBConnection();



        // Ensure column exists

        ensureChurchTypeColumn($conn);



        $stmt = $conn->prepare("UPDATE churches SET church_name = ?, admin_email = ?, church_type = ? WHERE id = ?");

        $stmt->bind_param("sssi", $churchName, $adminEmail, $churchType, $churchId);



        if ($stmt->execute()) {

            // Update session if this is the currently logged-in church

            if (isset($_SESSION['church_id']) && intval($_SESSION['church_id']) === $churchId) {

                $_SESSION['church_type'] = $churchType;

                $_SESSION['church_name'] = $churchName;

            }

            sendJSON(['success' => true, 'message' => 'تم تحديث الكنيسة بنجاح', 'church_type' => $churchType]);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في تحديث الكنيسة']);

        }



    } catch (Exception $e) {

        error_log("updateChurch error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحديث الكنيسة']);

    }

}



// ===== ADMIN: UPDATE CHURCH PASSWORD =====

function updateChurchPassword()

{

    checkAuth();



    try {

        $role = $_SESSION['uncle_role'] ?? 'uncle';

        $churchId = intval($_POST['church_id'] ?? 0);

        $newPassword = $_POST['new_password'] ?? '';

        $currentPassword = $_POST['current_password'] ?? '';



        $isDeveloper = ($role === 'developer' || $role === 'dev');

        $isChurchAdmin = ($role === 'admin' || (isset($_SESSION['church_id']) && intval($_SESSION['church_id']) === $churchId));



        if (!$isDeveloper && !$isChurchAdmin) {

            sendJSON(['success' => false, 'message' => 'غير مصرح']);

            return;

        }



        if ($churchId === 0 || empty($newPassword)) {

            sendJSON(['success' => false, 'message' => 'بيانات غير كاملة']);

            return;

        }



        $conn = getDBConnection();



        if (!$isDeveloper) {

            if (empty($currentPassword)) {

                sendJSON(['success' => false, 'message' => 'يجب إدخال كلمة المرور الحالية للكنيسة']);

                return;

            }

            $stmt = $conn->prepare("SELECT password_hash FROM churches WHERE id = ?");

            $stmt->bind_param("i", $churchId);

            $stmt->execute();

            $res = $stmt->get_result();

            if ($row = $res->fetch_assoc()) {

                $expectedHash = $row['password_hash'];

                if (hash('sha256', $currentPassword) !== $expectedHash) {

                    sendJSON(['success' => false, 'message' => 'كلمة المرور الحالية غير صحيحة']);

                    return;

                }

            } else {

                sendJSON(['success' => false, 'message' => 'الكنيسة غير موجودة']);

                return;

            }

        }



        $passwordHash = hash('sha256', $newPassword);

        $stmt = $conn->prepare("UPDATE churches SET password_hash = ? WHERE id = ?");

        $stmt->bind_param("si", $passwordHash, $churchId);



        if ($stmt->execute()) {

            // ► AUDIT

            auditChurchPasswordChange($churchId, '');



            sendJSON(['success' => true, 'message' => 'تم تحديث كلمة المرور بنجاح']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في تحديث كلمة المرور']);

        }



    } catch (Exception $e) {

        error_log("updateChurchPassword error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحديث كلمة المرور']);

    }

}



// ===== DEVELOPER: CLEANUP ORPHANED FILES =====

/**

 * Scans upload directories and compares against DB references.

 * Dry-run by default — only deletes with confirm=true.

 * Developer-only access.

 */

function cleanupOrphanedFiles()

{

    checkAuth();



    try {

        $role = $_SESSION['uncle_role'] ?? 'uncle';

        if ($role !== 'developer') {

            sendJSON(['success' => false, 'message' => 'غير مصرح — للمطورين فقط']);

            return;

        }



        $confirm = ($_POST['confirm'] ?? 'false') === 'true';

        $conn = getDBConnection();



        // ── Collect all image URLs referenced in DB ──

        $dbUrls = [];



        // Students

        $res = $conn->query("SELECT image_url FROM students WHERE image_url IS NOT NULL AND image_url != ''");

        while ($row = $res->fetch_assoc()) {

            $dbUrls[] = $row['image_url'];

        }



        // Uncles (include soft-deleted — their images may still be referenced)

        $res = $conn->query("SELECT image_url FROM uncles WHERE image_url IS NOT NULL AND image_url != ''");

        while ($row = $res->fetch_assoc()) {

            $dbUrls[] = $row['image_url'];

        }



        // Trips

        $res = $conn->query("SELECT image_url FROM trips WHERE image_url IS NOT NULL AND image_url != ''");

        while ($row = $res->fetch_assoc()) {

            $dbUrls[] = $row['image_url'];

        }



        // Pending registrations

        $res = $conn->query("SELECT image_url FROM pending_registrations WHERE image_url IS NOT NULL AND image_url != ''");

        while ($row = $res->fetch_assoc()) {

            $dbUrls[] = $row['image_url'];

        }



        // Normalize all DB URLs to just filenames for comparison

        $dbFilenames = [];

        foreach ($dbUrls as $url) {

            $path = $url;

            if (strpos($path, 'http') === 0) {

                $parsed = parse_url($path);

                $path = $parsed['path'] ?? '';

            }

            $dbFilenames[] = basename($path);

        }

        $dbFilenameSet = array_flip($dbFilenames);



        // ── Scan upload directories ──

        $uploadDirs = [

            'students' => __DIR__ . '/uploads/students/',

            'uncle'    => __DIR__ . '/uploads/uncle/',

            'trips'    => __DIR__ . '/uploads/trips/',

            'profiles' => __DIR__ . '/uploads/profiles/',

        ];



        $orphaned = [];

        $totalFiles = 0;

        $totalOrphanedSize = 0;



        foreach ($uploadDirs as $dirName => $dirPath) {

            if (!is_dir($dirPath)) continue;



            $files = @scandir($dirPath);

            if (!$files) continue;



            foreach ($files as $file) {

                if ($file === '.' || $file === '..') continue;

                $fullPath = $dirPath . $file;

                if (!is_file($fullPath)) continue;



                $totalFiles++;



                // Check if this file is referenced in the DB

                if (!isset($dbFilenameSet[$file])) {

                    $fileSize = @filesize($fullPath);

                    $orphaned[] = [

                        'directory' => $dirName,

                        'filename'  => $file,

                        'size'      => $fileSize,

                        'sizeHuman' => round($fileSize / 1024, 1) . ' KB',

                        'path'      => '/uploads/' . $dirName . '/' . $file,

                    ];

                    $totalOrphanedSize += $fileSize;

                }

            }

        }



        // ── Delete if confirmed ──

        $deletedCount = 0;

        if ($confirm && count($orphaned) > 0) {

            foreach ($orphaned as &$item) {

                $deleted = deleteUploadedFile($item['path']);

                $item['deleted'] = $deleted;

                if ($deleted) $deletedCount++;

            }

            unset($item);

        }



        sendJSON([

            'success'            => true,

            'dryRun'             => !$confirm,

            'totalFilesOnDisk'   => $totalFiles,

            'totalReferencedInDB'=> count($dbFilenames),

            'orphanedCount'      => count($orphaned),

            'orphanedSizeTotal'  => round($totalOrphanedSize / 1024, 1) . ' KB',

            'deletedCount'       => $deletedCount,

            'orphanedFiles'      => $orphaned,

            'message'            => $confirm

                ? "تم حذف $deletedCount من " . count($orphaned) . " ملف يتيم"

                : count($orphaned) . " ملف يتيم — أرسل confirm=true للحذف الفعلي",

        ]);



    } catch (Exception $e) {

        error_log("cleanupOrphanedFiles error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);

    }

}



// ===== ADMIN: DELETE CHURCH =====

function deleteChurch()

{

    checkAuth();



    try {

        $role = $_SESSION['uncle_role'] ?? 'uncle';



        if ($role !== 'developer') {

            sendJSON(['success' => false, 'message' => 'غير مصرح']);

        }



        $churchId = intval($_POST['church_id'] ?? 0);

        $churchCode = sanitize($_POST['church_code'] ?? '');



        if ($churchId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف الكنيسة مطلوب']);

        }



        // Add confirmation with church code

        if (empty($churchCode)) {

            sendJSON(['success' => false, 'message' => 'يرجى إدخال رمز الكنيسة للتأكيد']);

        }



        $conn = getDBConnection();



        // Verify church code

        $checkStmt = $conn->prepare("SELECT church_code FROM churches WHERE id = ?");

        $checkStmt->bind_param("i", $churchId);

        $checkStmt->execute();

        $result = $checkStmt->get_result();



        if ($row = $result->fetch_assoc()) {

            if ($row['church_code'] !== $churchCode) {

                sendJSON(['success' => false, 'message' => 'رمز الكنيسة غير صحيح']);

            }

        }



        // ── Collect all image URLs BEFORE deleting the church ──

        $imageUrls = [];



        // Student images

        $imgStmt = $conn->prepare("SELECT image_url FROM students WHERE church_id = ? AND image_url IS NOT NULL AND image_url != ''");

        $imgStmt->bind_param("i", $churchId);

        $imgStmt->execute();

        $imgResult = $imgStmt->get_result();

        while ($imgRow = $imgResult->fetch_assoc()) {

            $imageUrls[] = $imgRow['image_url'];

        }



        // Uncle images

        $imgStmt = $conn->prepare("SELECT image_url FROM uncles WHERE church_id = ? AND image_url IS NOT NULL AND image_url != ''");

        $imgStmt->bind_param("i", $churchId);

        $imgStmt->execute();

        $imgResult = $imgStmt->get_result();

        while ($imgRow = $imgResult->fetch_assoc()) {

            $imageUrls[] = $imgRow['image_url'];

        }



        // Trip images

        $imgStmt = $conn->prepare("SELECT image_url FROM trips WHERE church_id = ? AND image_url IS NOT NULL AND image_url != ''");

        $imgStmt->bind_param("i", $churchId);

        $imgStmt->execute();

        $imgResult = $imgStmt->get_result();

        while ($imgRow = $imgResult->fetch_assoc()) {

            $imageUrls[] = $imgRow['image_url'];

        }



        // Pending registration images (both image_url and profile_photo)

        $imgStmt = $conn->prepare("SELECT image_url FROM pending_registrations WHERE church_id = ? AND image_url IS NOT NULL AND image_url != ''");

        $imgStmt->bind_param("i", $churchId);

        $imgStmt->execute();

        $imgResult = $imgStmt->get_result();

        while ($imgRow = $imgResult->fetch_assoc()) {

            $imageUrls[] = $imgRow['image_url'];

        }



        error_log("deleteChurch: collected " . count($imageUrls) . " image URLs for cleanup (church_id=$churchId)");



        // Delete church (in real scenario, you might want to soft delete)

        $stmt = $conn->prepare("DELETE FROM churches WHERE id = ?");

        $stmt->bind_param("i", $churchId);



        if ($stmt->execute()) {

            // ── Clean up all collected image files AFTER successful DB deletion ──

            $deletedCount = 0;

            foreach ($imageUrls as $url) {

                if (deleteUploadedFile($url)) {

                    $deletedCount++;

                }

            }

            error_log("deleteChurch: cleaned up $deletedCount/" . count($imageUrls) . " image files (church_id=$churchId)");



            sendJSON(['success' => true, 'message' => 'تم حذف الكنيسة بنجاح']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في حذف الكنيسة']);

        }



    } catch (Exception $e) {

        error_log("deleteChurch error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في حذف الكنيسة']);

    }

}

function submitRegistrationRequest()

{

    try {

        $churchId = intval($_POST['churchId'] ?? 0);

        $name = sanitize($_POST['name'] ?? '');

        $class = sanitize($_POST['class'] ?? '');

        $birthday = sanitize($_POST['birthday'] ?? '');

        $phone = sanitize($_POST['phone'] ?? '');

        $email = sanitize($_POST['email'] ?? '');

        $address = sanitize($_POST['address'] ?? '');

        $username = sanitize($_POST['username'] ?? '');

        $password = $_POST['password'] ?? '';

        $profilePicBase64 = $_POST['profile_pic'] ?? '';



        // Extra custom fields sent as JSON string

        $extraDataRaw = $_POST['extra_data'] ?? '';

        $extraData = [];

        if ($extraDataRaw) {

            $decoded = json_decode($extraDataRaw, true);

            if (is_array($decoded))

                $extraData = $decoded;

        }

        $extraDataJson = !empty($extraData) ? json_encode($extraData, JSON_UNESCAPED_UNICODE) : null;



        error_log("📝 تسجيل جديد - الفصل المستلم: '$class'");

        error_log("📝 الفصل (hex): " . bin2hex($class));



        if ($churchId === 0 || empty($name) || empty($class)) {

            sendJSON(['success' => false, 'message' => 'البيانات المطلوبة ناقصة']);

            return;

        }



        $gender = sanitize($_POST['gender'] ?? '');

        if ($gender !== 'male' && $gender !== 'female') {

            $gender = detectGenderFromName($name);

        }



        $conn = getDBConnection();



        // التحقق من صحة الفصل — يدعم الفصول المخصصة والافتراضية

        $customStmt = $conn->prepare("

            SELECT id, arabic_name FROM church_classes

            WHERE church_id = ? AND arabic_name = ? AND is_active = 1

        ");

        $customStmt->bind_param("is", $churchId, $class);

        $customStmt->execute();

        $classValid = $customStmt->get_result()->num_rows > 0;



        if (!$classValid) {

            $defaultStmt = $conn->prepare("SELECT id FROM classes WHERE arabic_name = ?");

            $defaultStmt->bind_param("s", $class);

            $defaultStmt->execute();

            $classValid = $defaultStmt->get_result()->num_rows > 0;

        }



        if (!$classValid) {

            $allClasses = getClassesForChurch($churchId);

            $classNames = array_column($allClasses, 'arabic_name');

            error_log("⚠️ فصل غير صالح: '$class'");

            if (!empty($allClasses)) {

                sendJSON(['success' => false, 'message' => 'الفصل غير صحيح، الرجاء اختيار فصل من القائمة']);

                return;

            }

        }



        // تحويل تنسيق التاريخ

        $formattedBirthday = formatDateToDB($birthday);

        if (!$formattedBirthday && !empty($birthday)) {

            sendJSON(['success' => false, 'message' => 'تاريخ الميلاد غير صحيح. استخدم DD/MM/YYYY']);

            return;

        }



        // ── Hash password (optional) ────────────────────────────────

        // If password is empty we store NULL so DB unique indexes on username

        // or other fields don't conflict with empty strings.

        $passwordHash = !empty($password) ? hash('sha256', $password) : null;



        // Normalize username: treat empty or whitespace-only as NULL

        $username = trim((string) $username);

        if ($username === '')

            $username = null;



        // ── Save profile picture ───────────────────────────────────

        $imageUrl = null;

        if (!empty($profilePicBase64)) {

            // Expect data:image/...;base64,...

            if (preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,(.+)$/', $profilePicBase64, $m)) {

                $ext = $m[1] === 'jpg' ? 'jpeg' : $m[1];

                $imgData = base64_decode($m[2]);

                if ($imgData && strlen($imgData) < 3 * 1024 * 1024) {

                    $uploadDir = __DIR__ . '/uploads/profiles/';

                    if (!is_dir($uploadDir))

                        @mkdir($uploadDir, 0755, true);

                    $filename = 'reg_' . $churchId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

                    if (file_put_contents($uploadDir . $filename, $imgData) !== false) {

                        $imageUrl = '/uploads/profiles/' . $filename;

                    }

                }

            }

        }



        // ── Check if auto-approval is enabled for this church ──────

        $autoApprove = false;

        $settingsStmt = $conn->prepare("SELECT custom_field FROM church_settings WHERE church_id = ? LIMIT 1");

        $settingsStmt->bind_param("i", $churchId);

        $settingsStmt->execute();

        $settingsRow = $settingsStmt->get_result()->fetch_assoc();

        // auto_kids_approval is stored in churches.settings JSON

        $churchSettingsStmt = $conn->prepare("SELECT settings FROM churches WHERE id = ? LIMIT 1");

        $churchSettingsStmt->bind_param("i", $churchId);

        $churchSettingsStmt->execute();

        $churchSettingsRow = $churchSettingsStmt->get_result()->fetch_assoc();

        if ($churchSettingsRow && !empty($churchSettingsRow['settings'])) {

            $churchSettings = json_decode($churchSettingsRow['settings'], true);

            $autoApprove = !empty($churchSettings['auto_kids_approval']);

        }



        // Ensure extra_data column exists

        $conn->query("ALTER TABLE pending_registrations ADD COLUMN IF NOT EXISTS extra_data LONGTEXT DEFAULT NULL");

        $conn->query("ALTER TABLE pending_registrations ADD COLUMN IF NOT EXISTS username VARCHAR(100) DEFAULT NULL");

        $conn->query("ALTER TABLE pending_registrations ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) DEFAULT NULL");

        $conn->query("ALTER TABLE pending_registrations ADD COLUMN IF NOT EXISTS image_url TEXT DEFAULT NULL");



        if ($autoApprove) {

            // ── AUTO-APPROVE: Insert directly into students ────────

            $conn->begin_transaction();



            // Resolve class_id

            $classId = null;

            $finalClassName = null;

            $cs = $conn->prepare("SELECT id, arabic_name FROM church_classes WHERE church_id=? AND arabic_name=? AND is_active=1");

            $cs->bind_param("is", $churchId, $class);

            $cs->execute();

            if ($r = $cs->get_result()->fetch_assoc()) {

                $classId = $r['id'];

                $finalClassName = $r['arabic_name'];

            }

            if (!$classId) {

                $gs = $conn->prepare("SELECT id, arabic_name FROM classes WHERE arabic_name=?");

                $gs->bind_param("s", $class);

                $gs->execute();

                if ($r = $gs->get_result()->fetch_assoc()) {

                    $classId = $r['id'];

                    $finalClassName = $r['arabic_name'];

                }

            }

            if (!$classId) {

                $allClasses = getClassesForChurch($churchId);

                if (!empty($allClasses)) {

                    $classId = $allClasses[0]['id'];

                    $finalClassName = $allClasses[0]['arabic_name'];

                } else {

                    $conn->rollback();

                    sendJSON(['success' => false, 'message' => 'لا توجد فصول متاحة']);

                    return;

                }

            }



            // Check username uniqueness

            if (!empty($username)) {

                $uCheck = $conn->prepare("SELECT id FROM students WHERE church_id=? AND JSON_UNQUOTE(JSON_EXTRACT(custom_info,'$.username'))=? LIMIT 1");

                if ($uCheck) {

                    $uCheck->bind_param("is", $churchId, $username);

                    $uCheck->execute();

                    if ($uCheck->get_result()->num_rows > 0) {

                        $conn->rollback();

                        sendJSON(['success' => false, 'message' => 'اسم المستخدم مستخدم بالفعل، اختر اسماً آخر']);

                        return;

                    }

                }

            }



            $customInfo = !empty($username) ? json_encode(['username' => $username], JSON_UNESCAPED_UNICODE) : json_encode([], JSON_UNESCAPED_UNICODE);



            $ins = $conn->prepare("

                INSERT INTO students

                (church_id, name, class_id, class, address, phone, email, birthday,

                 image_url, password_hash, custom_info, gender,

                 commitment_coupons, coupons, attendance_coupons, created_at)

                VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 0,0,0, NOW())

            ");

            $ins->bind_param(

                "isisssssssss",

                $churchId,

                $name,

                $classId,

                $finalClassName,

                $address,

                $phone,

                $email,

                $formattedBirthday,

                $imageUrl,

                $passwordHash,

                $customInfo,

                $gender

            );

            if (!$ins->execute()) {

                $conn->rollback();

                sendJSON(['success' => false, 'message' => 'فشل في إضافة الطفل: ' . $ins->error]);

                return;

            }

            $newStudentId = $conn->insert_id;



            // Also record in pending_registrations as approved for audit

            $pendStmt = $conn->prepare("

                INSERT INTO pending_registrations

                (church_id,name,class,birthday,phone,email,address,extra_data,username,password_hash,image_url,status,gender,approved_at,created_at)

                VALUES (?,?,?,?,?,?,?,?,?,?,?,'approved',?,NOW(),NOW())

            ");

            $pendStmt->bind_param(

                "isssssssssss",

                $churchId,

                $name,

                $class,

                $formattedBirthday,

                $phone,

                $email,

                $address,

                $extraDataJson,

                $username,

                $passwordHash,

                $imageUrl,

                $gender

            );

            $pendStmt->execute();

            $registrationId = $conn->insert_id;



            $conn->commit();



            // ── Notification: new kid auto-added ──────────────────

            $churchName = getChurchName($churchId);

            pushNotification(

                $conn,

                $churchId,

                'registration',

                'طفل جديد في الفصل 🎉',

                "تم إضافة $name تلقائياً إلى فصل $finalClassName",

                'student',

                $newStudentId,

                $classId

            );



            sendJSON([

                'success' => true,

                'auto_approved' => true,

                'registration_id' => $registrationId,

                'student_id' => $newStudentId,

                'class_id' => $classId,

                'message' => 'تم القبول تلقائياً وإضافة الطفل'

            ]);



        } else {

            // ── NORMAL: Insert into pending_registrations ──────────

            $stmt = $conn->prepare("

                INSERT INTO pending_registrations

                (church_id,name,class,birthday,phone,email,address,extra_data,username,password_hash,image_url,status,gender,created_at)

                VALUES (?,?,?,?,?,?,?,?,?,?,?,'pending',?,NOW())

            ");

            $stmt->bind_param(

                "isssssssssss",

                $churchId,

                $name,

                $class,

                $formattedBirthday,

                $phone,

                $email,

                $address,

                $extraDataJson,

                $username,

                $passwordHash,

                $imageUrl,

                $gender

            );



            if ($stmt->execute()) {

                $registrationId = $conn->insert_id;

                error_log("✅ تم حفظ التسجيل بنجاح - ID: $registrationId, الفصل: '$class'");



                $churchName = getChurchName($churchId);

                pushNotification(

                    $conn,

                    $churchId,

                    'registration',

                    'طلب تسجيل جديد',

                    "تم استلام طلب تسجيل جديد: $name — فصل: $class",

                    'registration',

                    $registrationId,

                    null

                );



                sendRegistrationEmails($churchId, $email, [

                    'registration_id' => $registrationId,

                    'church_name' => $churchName,

                    'name' => $name,

                    'class' => $class,

                    'birthday' => $birthday,

                    'phone' => $phone,

                    'email' => $email,

                    'address' => $address,

                    'extra_data' => $extraDataJson ?? '',

                    'timestamp' => date('Y-m-d H:i:s'),

                ]);



                sendJSON([

                    'success' => true,

                    'auto_approved' => false,

                    'registration_id' => $registrationId,

                    'message' => 'تم إرسال طلب التسجيل بنجاح'

                ]);

            } else {

                error_log("❌ فشل في حفظ التسجيل: " . $conn->error);

                sendJSON(['success' => false, 'message' => 'فشل في إرسال طلب التسجيل: ' . $conn->error]);

            }

        }



    } catch (Exception $e) {

        error_log("❌ submitRegistrationRequest error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في إرسال الطلب: ' . $e->getMessage()]);

    }

}

function getChurchName($churchId)

{

    $conn = getDBConnection();

    $stmt = $conn->prepare("SELECT church_name FROM churches WHERE id = ?");

    $stmt->bind_param("i", $churchId);

    $stmt->execute();

    $result = $stmt->get_result();

    $row = $result->fetch_assoc();

    return $row['church_name'] ?? 'مدارس الأحد';

}



function getChurchAdminEmail($churchId)

{

    $conn = getDBConnection();

    $stmt = $conn->prepare("SELECT admin_email FROM churches WHERE id = ?");

    $stmt->bind_param("i", $churchId);

    $stmt->execute();

    $result = $stmt->get_result();

    $row = $result->fetch_assoc();

    return $row['admin_email'] ?? null;

}



function sendRegistrationEmails($churchId, $userEmail, $registrationData)

{

    $churchAdminEmail = getChurchAdminEmail($churchId);



    if (!$churchAdminEmail) {

        error_log("⚠️ sendRegistrationEmails: لا يوجد admin_email للكنيسة ID=$churchId — لم يُرسل أي إيميل");

        return false;

    }



    error_log("📧 sendRegistrationEmails: إرسال إلى $churchAdminEmail للكنيسة ID=$churchId");



    // Google Apps Script URL

    $googleScriptUrl = 'https://script.google.com/macros/s/AKfycbxsDA0veJTA3C_2Bw47coffOagRigWwaZnyxWuGb_gSVUCWM958V1bUcaZDwfIHVZ7b1g/exec';



    try {

        // Send to admin

        $adminData = [

            'action' => 'submitRegistration',   // correct Apps Script action

            'church_email' => $churchAdminEmail,      // correct field name Apps Script reads

            'church_name' => $registrationData['church_name'],

            'name' => $registrationData['name'],

            'class' => $registrationData['class'],

            'birthday' => $registrationData['birthday'],

            'phone' => $registrationData['phone'],

            'email' => $registrationData['email'],

            'address' => $registrationData['address'],

            'registration_id' => $registrationData['registration_id'],

            'timestamp' => $registrationData['timestamp'],

            'extra_data' => $registrationData['extra_data'] ?? ''

        ];



        sendAsyncRequest($googleScriptUrl, $adminData);



        // Send confirmation to user if email provided

        if (!empty($userEmail)) {

            $userData = [

                'action' => 'sendConfirmationEmail',

                'user_email' => $userEmail,

                'name' => $registrationData['name'],

                'church_name' => $registrationData['church_name']

            ];



            sendAsyncRequest($googleScriptUrl, $userData);

        }



        return true;

    } catch (Exception $e) {

        error_log("Error sending emails: " . $e->getMessage());

        return false;

    }

}



function sendAsyncRequest($url, $data)

{

    // Google Apps Script redirects POST (302). Appending params to URL ensures

    // data survives the redirect. JSON body is also sent for doPost() handlers.

    $jsonBody = json_encode($data, JSON_UNESCAPED_UNICODE);

    $urlWithParams = $url . '?' . http_build_query($data);



    $ch = curl_init($urlWithParams);

    curl_setopt($ch, CURLOPT_POST, true);

    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [

        'Content-Type: application/json',

        'Content-Length: ' . strlen($jsonBody),

    ]);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);

    curl_setopt($ch, CURLOPT_FORBID_REUSE, true);



    $result = curl_exec($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $curlErr = curl_error($ch);

    curl_close($ch);



    if ($result === false || !empty($curlErr)) {

        error_log("❌ sendAsyncRequest curl error: $curlErr — URL: $url");

        return false;

    }



    error_log("✅ sendAsyncRequest HTTP $httpCode — response: " . substr($result, 0, 300));

    return true;

}



// ===== UNCLE LOGIN =====

function handleUncleLogin()

{

    $_SESSION['uncle_logged_in'] = true;

    $_SESSION['user_type'] = 'uncle';



    try {

        $username = sanitize($_POST['username'] ?? '');

        $password = $_POST['password_hash'] ?? $_POST['password'] ?? '';



        if (empty($username) || empty($password)) {

            sendJSON(['success' => false, 'message' => 'الرجاء إدخال اسم المستخدم وكلمة المرور']);

        }



        $passwordHash = hash('sha256', $password);



        try {

            $conn = getDBConnection();

            ensureChurchTypeColumn($conn);



            $stmt = $conn->prepare("

                SELECT u.id, u.church_id, u.name, u.username, u.password_hash,

                       u.image_url, u.role, c.church_name, c.church_code,

                       COALESCE(c.church_type, 'kids') AS church_type

                FROM uncles u

                LEFT JOIN churches c ON u.church_id = c.id

                WHERE u.username = ?

            ");

            $stmt->bind_param("s", $username);

            $stmt->execute();

            $row = $stmt->get_result()->fetch_assoc();



            if ($row && $passwordHash === $row['password_hash']) {

                $_SESSION['uncle_id'] = $row['id'];

                $_SESSION['church_id'] = $row['church_id'];

                $_SESSION['church_name'] = $row['church_name'];

                $_SESSION['church_code'] = $row['church_code'];

                $_SESSION['church_type'] = $row['church_type'];

                $_SESSION['uncle_name'] = $row['name'];

                $_SESSION['uncle_username'] = $row['username'];

                $_SESSION['uncle_image'] = $row['image_url'];

                $_SESSION['uncle_role'] = $row['role'];



                auditLogin('uncle', $row['id'], $row['name']);

                runBackgroundGradeUpChecks();



                sendJSON([

                    'success' => true,

                    'message' => 'تم تسجيل الدخول بنجاح',

                    'uncle' => [

                        'id' => $row['id'],

                        'name' => $row['name'],

                        'username' => $row['username'],

                        'image_url' => $row['image_url'],

                        'role' => $row['role']

                    ],

                    'church_name' => $row['church_name'],

                    'church_type' => $row['church_type'],

                ]);

            }

        } catch (Exception $e) {

            error_log("handleUncleLogin DB error: " . $e->getMessage());

        }



        sendJSON(['success' => false, 'message' => 'اسم المستخدم أو كلمة المرور غير صحيحة']);



    } catch (Exception $e) {

        error_log("Uncle login error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تسجيل الدخول']);

    }

}



// ===== GET CURRENT UNCLE =====

function getCurrentUncle()

{

    // Accept both uncle sessions AND church sessions (for admin uncle data fetch)

    checkUncleAuth();



    $uncleId = intval($_SESSION['uncle_id'] ?? 0);

    if (!$uncleId) {

        sendJSON(['success' => false, 'message' => 'لا يوجد خادم مسجّل الدخول']);

        return;

    }



    // Always fetch fresh data from DB so image_url / name reflect latest edits

    try {

        $conn = getDBConnection();

        $stmt = $conn->prepare("SELECT id, name, username, image_url, role FROM uncles WHERE id = ? AND (deleted IS NULL OR deleted = 0) LIMIT 1");

        $stmt->bind_param("i", $uncleId);

        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();



        if ($row) {

            // Sync session with fresh DB values

            $_SESSION['uncle_name'] = $row['name'];

            $_SESSION['uncle_username'] = $row['username'];

            $_SESSION['uncle_image'] = $row['image_url'];

            $_SESSION['uncle_role'] = $row['role'];



            // Also include assigned classes for this uncle (if any)

            $assignedClasses = [];

            try {

                $assignedClasses = getUncleClasses((int) $row['id']);

            } catch (Exception $e) {

                error_log("getCurrentUncle - failed to load assigned classes: " . $e->getMessage());

            }



            sendJSON([

                'success' => true,

                'uncle' => [

                    'id' => (int) $row['id'],

                    'name' => $row['name'],

                    'username' => $row['username'],

                    'image_url' => $row['image_url'] ?? '',

                    'role' => $row['role'] ?? 'uncle',

                    'classes' => $assignedClasses

                ]

            ]);

        } else {

            // Fallback to session values if DB fetch fails

            $fallbackClasses = [];

            if ($uncleId) {

                try {

                    $fallbackClasses = getUncleClasses($uncleId);

                } catch (Exception $e) {

                }

            }

            sendJSON([

                'success' => true,

                'uncle' => [

                    'id' => $uncleId,

                    'name' => $_SESSION['uncle_name'] ?? '',

                    'username' => $_SESSION['uncle_username'] ?? '',

                    'image_url' => $_SESSION['uncle_image'] ?? '',

                    'role' => $_SESSION['uncle_role'] ?? 'uncle',

                    'classes' => $fallbackClasses

                ]

            ]);

        }

    } catch (Exception $e) {

        error_log("getCurrentUncle DB error: " . $e->getMessage());

        sendJSON([

            'success' => true,

            'uncle' => [

                'id' => $uncleId,

                'name' => $_SESSION['uncle_name'] ?? '',

                'username' => $_SESSION['uncle_username'] ?? '',

                'image_url' => $_SESSION['uncle_image'] ?? '',

                'role' => $_SESSION['uncle_role'] ?? 'uncle',

            ]

        ]);

    }

}



// ===== UPDATE UNCLE PROFILE =====

function updateUncleProfile()

{

    checkUncleAuth(); // Use specific uncle auth



    try {

        $uncleId = $_SESSION['uncle_id'];

        $name = sanitize($_POST['name'] ?? '');

        $username = sanitize($_POST['username'] ?? '');

        $newPassword = $_POST['new_password'] ?? '';



        if (empty($name) || empty($username)) {

            sendJSON(['success' => false, 'message' => 'الاسم واسم المستخدم مطلوبان']);

        }



        $conn = getDBConnection();



        // Check if username is taken by another user

        $checkStmt = $conn->prepare("SELECT id FROM uncles WHERE username = ? AND id != ?");

        $checkStmt->bind_param("si", $username, $uncleId);

        $checkStmt->execute();

        if ($checkStmt->get_result()->num_rows > 0) {

            sendJSON(['success' => false, 'message' => 'اسم المستخدم مستخدم بالفعل']);

        }



        if (!empty($newPassword)) {

            $passwordHash = hash('sha256', $newPassword);

            $stmt = $conn->prepare("

                UPDATE uncles 

                SET name = ?, username = ?, password_hash = ?, updated_at = NOW()

                WHERE id = ?

            ");

            $stmt->bind_param("sssi", $name, $username, $passwordHash, $uncleId);

        } else {

            $stmt = $conn->prepare("

                UPDATE uncles 

                SET name = ?, username = ?, updated_at = NOW()

                WHERE id = ?

            ");

            $stmt->bind_param("ssi", $name, $username, $uncleId);

        }



        if ($stmt->execute()) {

            // Update session

            $_SESSION['uncle_name'] = $name;

            $_SESSION['uncle_username'] = $username;



            sendJSON([

                'success' => true,

                'message' => 'تم تحديث الملف الشخصي بنجاح',

                'uncle' => [

                    'id' => $uncleId,

                    'name' => $name,

                    'username' => $username

                ]

            ]);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في تحديث الملف الشخصي: ' . $conn->error]);

        }



    } catch (Exception $e) {

        error_log("updateUncleProfile error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحديث الملف الشخصي']);

    }

}

// ===== UPDATE UNCLE IMAGE =====

function updateUncleImage()

{

    checkAuth();



    try {

        $uncleId = $_SESSION['uncle_id'];

        $imageUrl = sanitize($_POST['imageUrl'] ?? '');



        $conn = getDBConnection();

        $stmt = $conn->prepare("UPDATE uncles SET image_url = ? WHERE id = ?");

        $stmt->bind_param("si", $imageUrl, $uncleId);



        if ($stmt->execute()) {

            $_SESSION['uncle_image'] = $imageUrl;

            sendJSON(['success' => true, 'message' => 'تم تحديث الصورة بنجاح']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في تحديث الصورة']);

        }



    } catch (Exception $e) {

        error_log("updateUncleImage error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحديث الصورة']);

    }

}



// ===== ADMIN: GET ALL UNCLES =====

function getAllUncles()

{

    checkAuth();



    try {

        $churchId = getChurchId();



        if ($churchId === 0 && isset($_SESSION['church_id'])) {

            $churchId = $_SESSION['church_id'];

        }



        error_log("getAllUncles - Church ID: " . $churchId);



        if ($churchId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف الكنيسة غير موجود']);

            return;

        }



        $conn = getDBConnection();



        $isAll = (!empty($_POST['all_churches']) && $_POST['all_churches'] === '1');



        if ($isAll) {

            $stmt = $conn->prepare("

                SELECT u.id, u.church_id, u.name, u.username, u.image_url, u.role, u.gender, u.phone, u.created_at,

                       c.church_name

                FROM uncles u

                LEFT JOIN churches c ON u.church_id = c.id

                WHERE (u.deleted IS NULL OR u.deleted = 0)

                ORDER BY c.church_name,

                    CASE u.role 

                        WHEN 'admin' THEN 1

                        WHEN 'developer' THEN 2

                        ELSE 3

                    END, u.name

            ");

        } else {

            $stmt = $conn->prepare("

                SELECT u.id, u.church_id, u.name, u.username, u.image_url, u.role, u.gender, u.phone, u.created_at

                FROM uncles u

                WHERE u.church_id = ? AND (u.deleted IS NULL OR u.deleted = 0)

                ORDER BY 

                    CASE u.role 

                        WHEN 'admin' THEN 1

                        WHEN 'developer' THEN 2

                        ELSE 3

                    END, u.name

            ");

            $stmt->bind_param("i", $churchId);

        }



        $stmt->execute();

        $result = $stmt->get_result();



        $uncles = [];

        while ($row = $result->fetch_assoc()) {

            $row['church_name'] = $row['church_name'] ?? '';



            // Get uncle's assigned classes

            $row['classes'] = getUncleClasses($row['id']);



            // For backward compatibility, also store as comma-separated string

            $classNames = array_column($row['classes'], 'class_name');

            $row['class'] = !empty($classNames) ? implode(', ', $classNames) : '';



            $row['is_active'] = 1;

            $uncles[] = $row;

        }



        error_log("Found " . count($uncles) . " uncles");



        sendJSON(['success' => true, 'uncles' => $uncles]);



    } catch (Exception $e) {

        error_log("getAllUncles error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في جلب المستخدمين: ' . $e->getMessage()]);

    }

}



// Modified addUncle function

function addUncle()

{

    checkAuth();



    try {

        $churchId = intval($_POST['church_id'] ?? 0);



        if ($churchId === 0) {

            $churchId = getChurchId();

            if ($churchId === 0 && isset($_SESSION['church_id'])) {

                $churchId = $_SESSION['church_id'];

            }

        }



        $name = sanitize($_POST['name'] ?? '');

        $username = sanitize($_POST['username'] ?? '');

        $password = $_POST['password'] ?? '';

        $uncleRole = sanitize($_POST['role'] ?? 'uncle');

        $classes = isset($_POST['classes']) ? json_decode($_POST['classes'], true) : [];



        if (empty($name) || empty($username) || empty($password) || $churchId === 0) {

            sendJSON(['success' => false, 'message' => 'البيانات المطلوبة ناقصة']);

            return;

        }



        // Check if church exists

        $conn = getDBConnection();

        $churchCheck = $conn->prepare("SELECT id FROM churches WHERE id = ?");

        $churchCheck->bind_param("i", $churchId);

        $churchCheck->execute();

        if ($churchCheck->get_result()->num_rows === 0) {

            sendJSON(['success' => false, 'message' => 'الكنيسة غير موجودة']);

            return;

        }



        // Check if username already exists

        $userCheck = $conn->prepare("SELECT id FROM uncles WHERE username = ?");

        $userCheck->bind_param("s", $username);

        $userCheck->execute();

        if ($userCheck->get_result()->num_rows > 0) {

            sendJSON(['success' => false, 'message' => 'اسم المستخدم موجود بالفعل']);

            return;

        }



        $passwordHash = hash('sha256', $password);



        $gender = sanitize($_POST['gender'] ?? '');

        if ($gender !== 'male' && $gender !== 'female') {

            $gender = detectGenderFromName($name);

        }

        $phone = sanitize($_POST['phone'] ?? '');



        // Insert uncle

        $stmt = $conn->prepare("

            INSERT INTO uncles (church_id, name, username, password_hash, role, gender, phone)

            VALUES (?, ?, ?, ?, ?, ?, ?)

        ");

        $stmt->bind_param("issssss", $churchId, $name, $username, $passwordHash, $uncleRole, $gender, $phone);



        if ($stmt->execute()) {

            $newUncleId = $conn->insert_id;



            // Save classes if provided

            if (!empty($classes) && is_array($classes)) {

                saveUncleClasses($newUncleId, $churchId, $classes);

            }



            // Audit log

            writeAuditLog('uncle_add', 'uncle', $newUncleId, $name, null, [

                'name' => $name,

                'username' => $username,

                'role' => $uncleRole,

                'church_id' => $churchId,

                'classes' => $classes,

                'gender' => $gender,

                'phone' => $phone

            ], 'إضافة خادم جديد');



            sendJSON(['success' => true, 'message' => 'تم إضافة الخادم بنجاح']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في إضافة الخادم: ' . $conn->error]);

        }



    } catch (Exception $e) {

        error_log("addUncle error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في إضافة الخادم: ' . $e->getMessage()]);

    }

}



// Modified updateUncle function

function updateUncle()

{

    checkAuth();



    try {

        $uncleId = intval($_POST['uncle_id'] ?? 0);

        $name = sanitize($_POST['name'] ?? '');

        $username = sanitize($_POST['username'] ?? '');

        $newPassword = $_POST['new_password'] ?? '';

        $uncleRole = sanitize($_POST['role'] ?? 'uncle');

        $classes = isset($_POST['classes']) ? json_decode($_POST['classes'], true) : [];

        $churchId = getChurchId();



        if ($uncleId === 0 || empty($name) || empty($username) || $churchId === 0) {

            sendJSON(['success' => false, 'message' => 'بيانات غير كاملة']);

            return;

        }



        $conn = getDBConnection();



        // Verify this uncle belongs to the church

        $checkStmt = $conn->prepare("SELECT id FROM uncles WHERE id = ? AND church_id = ?");

        $checkStmt->bind_param("ii", $uncleId, $churchId);

        $checkStmt->execute();

        if ($checkStmt->get_result()->num_rows === 0) {

            sendJSON(['success' => false, 'message' => 'الخادم غير موجود أو لا ينتمي لهذه الكنيسة']);

            return;

        }



        // Get old uncle data for audit

        $oldData = [];

        $oldStmt = $conn->prepare("SELECT name, username, role, gender, phone FROM uncles WHERE id = ?");

        $oldStmt->bind_param("i", $uncleId);

        $oldStmt->execute();

        $oldResult = $oldStmt->get_result();

        if ($oldRow = $oldResult->fetch_assoc()) {

            $oldData = $oldRow;

        }



        // Prevent editing developer accounts unless the user is the same developer

        $isTargetDeveloper = in_array(strtolower($oldData['role'] ?? ''), ['developer', 'dev']);

        if ($isTargetDeveloper) {

            $callerId = $_SESSION['uncle_id'] ?? 0;

            if ($uncleId != $callerId) {

                sendJSON(['success' => false, 'message' => 'لا يمكنك تعديل حساب المطور']);

                return;

            }

        }



        $passwordChanged = !empty($newPassword);

        $gender = sanitize($_POST['gender'] ?? 'male');

        $phone = sanitize($_POST['phone'] ?? '');



        if (!empty($newPassword)) {

            $passwordHash = hash('sha256', $newPassword);

            $stmt = $conn->prepare("

                UPDATE uncles 

                SET name = ?, username = ?, password_hash = ?, role = ?, gender = ?, phone = ?

                WHERE id = ? AND church_id = ?

            ");

            $stmt->bind_param("ssssssii", $name, $username, $passwordHash, $uncleRole, $gender, $phone, $uncleId, $churchId);

        } else {

            $stmt = $conn->prepare("

                UPDATE uncles 

                SET name = ?, username = ?, role = ?, gender = ?, phone = ?

                WHERE id = ? AND church_id = ?

            ");

            $stmt->bind_param("sssssii", $name, $username, $uncleRole, $gender, $phone, $uncleId, $churchId);

        }



        if ($stmt->execute()) {

            // Update classes

            saveUncleClasses($uncleId, $churchId, $classes);



            // Get uncle name for audit

            $uncleName = $name;



            // Audit log

            $newData = ['name' => $name, 'username' => $username, 'role' => $uncleRole, 'gender' => $gender, 'phone' => $phone, 'classes' => $classes];

            writeAuditLog('uncle_edit', 'uncle', $uncleId, $uncleName, $oldData, $newData, 'تعديل بيانات خادم');



            if ($passwordChanged) {

                writeAuditLog('uncle_password', 'uncle', $uncleId, $uncleName, null, null, 'تغيير كلمة المرور');

            }



            sendJSON(['success' => true, 'message' => 'تم تحديث الخادم بنجاح']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في تحديث الخادم: ' . $conn->error]);

        }



    } catch (Exception $e) {

        error_log("updateUncle error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحديث الخادم: ' . $e->getMessage()]);

    }

}



// Modified deleteUncle function to clean up class assignments

function deleteUncle()

{

    checkAuth();



    try {

        $uncleId = intval($_POST['uncle_id'] ?? 0);

        $churchId = getChurchId();



        if ($uncleId === 0 || $churchId === 0) {

            sendJSON(['success' => false, 'message' => 'بيانات غير كاملة']);

            return;

        }



        // Prevent deleting yourself

        if (isset($_SESSION['uncle_id']) && $uncleId === $_SESSION['uncle_id']) {

            sendJSON(['success' => false, 'message' => 'لا يمكنك حذف حسابك الخاص']);

        }



        $conn = getDBConnection();



        // Verify this uncle belongs to the church

        $checkStmt = $conn->prepare("SELECT id, name, role, image_url FROM uncles WHERE id = ? AND church_id = ?");

        $checkStmt->bind_param("ii", $uncleId, $churchId);

        $checkStmt->execute();

        $result = $checkStmt->get_result();



        if ($result->num_rows === 0) {

            sendJSON(['success' => false, 'message' => 'الخادم غير موجود أو لا ينتمي لهذه الكنيسة']);

            return;

        }



        $uncleData = $result->fetch_assoc();

        $uncleName = $uncleData['name'];



        // Prevent deleting developer accounts

        $isTargetDeveloper = in_array(strtolower($uncleData['role'] ?? ''), ['developer', 'dev']);

        if ($isTargetDeveloper) {

            sendJSON(['success' => false, 'message' => 'لا يمكنك حذف حساب المطور']);

            return;

        }



        // Start transaction

        $conn->begin_transaction();



        try {

            // Delete class assignments first

            $deleteClassesStmt = $conn->prepare("DELETE FROM uncle_class_assignments WHERE uncle_id = ?");

            $deleteClassesStmt->bind_param("i", $uncleId);

            $deleteClassesStmt->execute();



            // Soft delete the uncle

            $stmt = $conn->prepare("UPDATE uncles SET deleted = 1 WHERE id = ? AND church_id = ?");

            $stmt->bind_param("ii", $uncleId, $churchId);

            $stmt->execute();



            $conn->commit();



            // Clean up uncle image file from disk

            deleteUploadedFile($uncleData['image_url'] ?? null);



            // Audit log

            writeAuditLog('uncle_delete', 'uncle', $uncleId, $uncleName, null, null, 'حذف خادم');



            sendJSON(['success' => true, 'message' => 'تم حذف الخادم بنجاح']);



        } catch (Exception $e) {

            $conn->rollback();

            throw $e;

        }



    } catch (Exception $e) {

        error_log("deleteUncle error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في حذف الخادم: ' . $e->getMessage()]);

    }



}



// ===== DEVELOPER: UPDATE CHURCH ADMIN EMAIL =====

function updateChurchAdminEmail()

{

    checkAuth();

    try {

        $role = $_SESSION['uncle_role'] ?? 'uncle';



        if ($role !== 'developer') {

            sendJSON(['success' => false, 'message' => 'غير مصرح']);

            return;

        }



        $churchId = intval($_POST['church_id'] ?? 0);

        $adminEmail = sanitize($_POST['admin_email'] ?? '');



        if ($churchId === 0 || empty($adminEmail)) {

            sendJSON(['success' => false, 'message' => 'بيانات غير كاملة']);

            return;

        }



        $conn = getDBConnection();

        $stmt = $conn->prepare("UPDATE churches SET admin_email = ? WHERE id = ?");

        $stmt->bind_param("si", $adminEmail, $churchId);



        if ($stmt->execute()) {

            sendJSON(['success' => true, 'message' => 'تم تحديث البريد الإلكتروني للمسؤول']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في التحديث']);

        }



    } catch (Exception $e) {

        error_log("updateChurchAdminEmail error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في التحديث']);

    }

}



// ===== GENERATE KIDS TEMPLATE =====

function generateKidsTemplate()

{

    try {

        $churchId = getChurchId();



        // ── Get the actual classes for this church (custom first, then default) ──

        $churchClasses = [];

        if ($churchId > 0) {

            $churchClasses = getClassesForChurch($churchId);

        }



        // Fallback to global default if no classes found

        if (empty($churchClasses)) {

            $conn = getDBConnection();

            $result = $conn->query("SELECT arabic_name FROM classes ORDER BY display_order");

            while ($r = $result->fetch_assoc()) {

                $churchClasses[] = ['arabic_name' => $r['arabic_name']];

            }

        }



        // ── Get custom field definitions for this church ─────────

        $customFields = [];

        if ($churchId > 0) {

            $conn = getDBConnection();

            ensureChurchSettingsTable($conn);

            $cfStmt = $conn->prepare("SELECT custom_field FROM church_settings WHERE church_id = ? LIMIT 1");

            $cfStmt->bind_param("i", $churchId);

            $cfStmt->execute();

            $cfRow = $cfStmt->get_result()->fetch_assoc();

            if ($cfRow && !empty($cfRow['custom_field'])) {

                $cfData = json_decode($cfRow['custom_field'], true);

                if (is_array($cfData)) {

                    if (isset($cfData[0]) && is_array($cfData[0])) {

                        // Array of fields

                        foreach ($cfData as $f) {

                            if (!empty($f['name']))

                                $customFields[] = $f['name'];

                        }

                    } elseif (!empty($cfData['name'])) {

                        // Single legacy field

                        $customFields[] = $cfData['name'];

                    }

                }

            }

        }



        // ── Build CSV with actual class names ────────────────────

        // Quote all custom field names in the header to handle commas/special chars

        $quotedCustomHeaders = array_map(function ($n) {

            return '"' . str_replace('"', '""', $n) . '"';

        }, $customFields);

        $headerCols = $customFields

            ? 'class,name,address,phone,birthday,' . implode(',', $quotedCustomHeaders) . "\n"

            : "class,name,address,phone,birthday\n";

        $csvContent = $headerCols;

        $lastIdx = count($churchClasses) - 1;



        foreach ($churchClasses as $idx => $cls) {

            $cn = $cls['arabic_name'];

            $emptyCols = str_repeat(',""', count($customFields));

            for ($i = 1; $i <= 10; $i++) {

                $csvContent .= "\"$cn\",\"\",\"\",\"\",\"\"{$emptyCols}\n";

            }

            if ($idx < $lastIdx)

                $csvContent .= "\n";

        }



        // ── Return as CSV download ────────────────────────────────

        header('Content-Type: text/csv; charset=utf-8');

        header('Content-Disposition: attachment; filename="kids_template_' . date('Y-m-d') . '.csv"');

        header('Cache-Control: no-cache, no-store, must-revalidate');



        // UTF-8 BOM for Excel Arabic support

        echo chr(0xEF) . chr(0xBB) . chr(0xBF);

        echo $csvContent;

        exit;



    } catch (Exception $e) {

        error_log("generateKidsTemplate error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في إنشاء القالب']);

    }

}



// ===== GENERATE EXCEL TEMPLATE =====

function generateExcelTemplate()

{

    try {

        require_once 'vendor/autoload.php'; // If using PhpSpreadsheet



        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();



        // Define all classes

        $classes = ['حضانة', 'أولى', 'تانية', 'تالتة', 'رابعة', 'خامسة', 'سادسة'];



        foreach ($classes as $index => $class) {

            if ($index > 0) {

                $spreadsheet->createSheet();

            }



            $sheet = $spreadsheet->setActiveSheetIndex($index);

            $sheet->setTitle($class);



            // Set headers

            $sheet->setCellValue('A1', 'الاسم');

            $sheet->setCellValue('B1', 'العنوان');

            $sheet->setCellValue('C1', 'الهاتف');

            $sheet->setCellValue('D1', 'تاريخ الميلاد (DD/MM/YYYY)');



            // Style headers

            $sheet->getStyle('A1:D1')->getFont()->setBold(true);

            $sheet->getStyle('A1:D1')->getFill()

                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)

                ->getStartColor()->setARGB('FF4F46E5');

            $sheet->getStyle('A1:D1')->getFont()->getColor()->setARGB('FFFFFFFF');



            // Set column widths

            $sheet->getColumnDimension('A')->setWidth(30);

            $sheet->getColumnDimension('B')->setWidth(40);

            $sheet->getColumnDimension('C')->setWidth(20);

            $sheet->getColumnDimension('D')->setWidth(25);



            // Add instructions

            $sheet->setCellValue('F1', 'تعليمات:');

            $sheet->setCellValue('F2', '1. املأ البيانات في الأعمدة A-D فقط');

            $sheet->setCellValue('F3', '2. تاريخ الميلاد: استخدم صيغة DD/MM/YYYY');

            $sheet->setCellValue('F4', '3. رقم الهاتف: ابدأ بـ 01XXXXXXXXX');

            $sheet->setCellValue('F5', '4. لا تغير تنسيق الأعمدة');

        }



        // Set first sheet as active

        $spreadsheet->setActiveSheetIndex(0);



        // Create Excel file

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);



        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        header('Content-Disposition: attachment;filename="kids_template_' . date('Y-m-d') . '.xlsx"');

        header('Cache-Control: max-age=0');



        $writer->save('php://output');

        exit;



    } catch (Exception $e) {

        // Fallback to CSV if Excel not available

        generateKidsTemplate();

    }

}



// ===== EXPORT ALL EXISTING KIDS AS CSV (pre-filled for editing) =====

function exportKidsData()

{

    try {

        $churchId = getChurchId();

        if (!$churchId) {

            sendJSON(['success' => false, 'message' => 'معرف الكنيسة مطلوب']);

            return;

        }



        $conn = getDBConnection();



        // ── Load custom field definitions ─────────────────────────

        ensureChurchSettingsTable($conn);

        $cfStmt = $conn->prepare("SELECT custom_field FROM church_settings WHERE church_id = ? LIMIT 1");

        $cfStmt->bind_param("i", $churchId);

        $cfStmt->execute();

        $cfRow = $cfStmt->get_result()->fetch_assoc();

        $customFieldDefs = [];

        if ($cfRow && !empty($cfRow['custom_field'])) {

            $cfData = json_decode($cfRow['custom_field'], true);

            if (is_array($cfData)) {

                if (isset($cfData[0]) && is_array($cfData[0]))

                    $customFieldDefs = $cfData;

                elseif (!empty($cfData['name']))

                    $customFieldDefs = [$cfData];

            }

        }



        // ── Load all students for this church ─────────────────────

        $stmt = $conn->prepare("

            SELECT s.id, s.name, s.address, s.phone, s.birthday, s.custom_info,

                   COALESCE(cc.arabic_name, gc.arabic_name, s.class) AS class_name

            FROM students s

            LEFT JOIN church_classes cc ON cc.id = s.class_id AND cc.church_id = s.church_id AND cc.is_active = 1

            LEFT JOIN classes gc        ON gc.id = s.class_id

            WHERE s.church_id = ?

              AND COALESCE(s.enrollment_status, 'active') = 'active'

            ORDER BY class_name, s.name

        ");

        $stmt->bind_param("i", $churchId);

        $stmt->execute();

        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);



        // ── Build CSV ─────────────────────────────────────────────

        // Columns: student_id, class, name, address, phone, birthday [, custom_fields...]

        // Quote custom field names to handle commas/special characters safely

        $customHeadersQuoted = array_map(function ($f) {

            $n = $f['name'] ?? '';

            return '"' . str_replace('"', '""', $n) . '"';

        }, $customFieldDefs);

        $headerLine = 'student_id,class,name,address,phone,birthday';

        if ($customHeadersQuoted)

            $headerLine .= ',' . implode(',', $customHeadersQuoted);

        $headerLine .= "\n";



        $csvContent = $headerLine;

        foreach ($rows as $r) {

            $id = (int) $r['id'];

            $class = $r['class_name'] ?? '';

            $name = $r['name'] ?? '';

            $address = $r['address'] ?? '';

            $phone = $r['phone'] ?? '';

            $birthday = $r['birthday'] ? date('d/m/Y', strtotime($r['birthday'])) : '';



            // Escape cell: wrap in quotes, escape internal quotes

            $esc = function ($v) {

                return '"' . str_replace('"', '""', $v) . '"';

            };



            $line = $id . ',' . $esc($class) . ',' . $esc($name) . ',' .

                $esc($address) . ',' . $esc($phone) . ',' . $esc($birthday);



            // Custom fields

            if ($customFieldDefs) {

                $info = !empty($r['custom_info']) ? json_decode($r['custom_info'], true) : [];

                if (!is_array($info))

                    $info = [];

                foreach ($customFieldDefs as $cfIdx => $cfDef) {

                    $key = $cfDef['key'] ?? ('field_' . $cfIdx);

                    // Support both new key format and legacy {'value':...}

                    $val = $info[$key] ?? ($info['field_' . $cfIdx] ?? ($cfIdx === 0 ? ($info['value'] ?? '') : ''));

                    $line .= ',' . $esc((string) $val);

                }

            }



            $csvContent .= $line . "\n";

        }



        header('Content-Type: text/csv; charset=utf-8');

        header('Content-Disposition: attachment; filename="kids_data_' . date('Y-m-d') . '.csv"');

        header('Cache-Control: no-cache, no-store, must-revalidate');

        echo chr(0xEF) . chr(0xBB) . chr(0xBF); // UTF-8 BOM for Excel

        echo $csvContent;

        exit;



    } catch (Exception $e) {

        error_log("exportKidsData error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تصدير البيانات: ' . $e->getMessage()]);

    }

}



function bulkAddKids()

{

    try {

        $churchId = getChurchId();

        $csvData = $_FILES['csvFile']['tmp_name'] ?? '';



        if (empty($csvData) || !file_exists($csvData)) {

            sendJSON(['success' => false, 'message' => 'الرجاء تحميل ملف CSV']);

            return;

        }



        $conn = getDBConnection();

        $conn->begin_transaction();



        // ── Read custom field definitions for this church ─────────

        $customFieldDefs = [];

        ensureChurchSettingsTable($conn);

        $cfStmt2 = $conn->prepare("SELECT custom_field FROM church_settings WHERE church_id = ? LIMIT 1");

        $cfStmt2->bind_param("i", $churchId);

        $cfStmt2->execute();

        $cfRow2 = $cfStmt2->get_result()->fetch_assoc();

        if ($cfRow2 && !empty($cfRow2['custom_field'])) {

            $cfData2 = json_decode($cfRow2['custom_field'], true);

            if (is_array($cfData2)) {

                if (isset($cfData2[0]) && is_array($cfData2[0]))

                    $customFieldDefs = $cfData2;

                elseif (!empty($cfData2['name']))

                    $customFieldDefs = [$cfData2];

            }

        }



        // ── Build class lookup map ────────────────────────────────

        $churchClasses = getClassesForChurch($churchId);

        $classMap = [];

        foreach ($churchClasses as $cls) {

            $key1 = mb_strtolower(trim($cls['arabic_name']));

            $key2 = mb_strtolower(trim($cls['code'] ?? ''));

            $classMap[$key1] = ['id' => $cls['id'], 'arabic_name' => $cls['arabic_name']];

            if (!empty($key2))

                $classMap[$key2] = ['id' => $cls['id'], 'arabic_name' => $cls['arabic_name']];

        }



        $addedCount = 0;

        $updatedCount = 0;

        $errorCount = 0;

        $skippedEmpty = 0;

        $errors = [];



        // ── Read CSV ──────────────────────────────────────────────

        $file = fopen($csvData, 'r');

        $bom = fread($file, 3);

        if ($bom !== chr(0xEF) . chr(0xBB) . chr(0xBF))

            rewind($file);



        // Read and detect header

        $headerRow = fgetcsv($file);

        $headerRow = array_map(function ($h) {

            return mb_strtolower(trim(str_replace('"', '', $h)));

        }, $headerRow);



        // Detect mode: does the CSV have a student_id column?

        $colStudentId = array_search('student_id', $headerRow);

        $isUpdateMode = $colStudentId !== false;



        // Detect column positions (flexible — works for both template and export formats)

        $colClass = array_search('class', $headerRow) !== false ? array_search('class', $headerRow) : ($isUpdateMode ? 1 : 0);

        $colName = array_search('name', $headerRow) !== false ? array_search('name', $headerRow) : ($isUpdateMode ? 2 : 1);

        $colAddress = array_search('address', $headerRow) !== false ? array_search('address', $headerRow) : ($isUpdateMode ? 3 : 2);

        $colPhone = array_search('phone', $headerRow) !== false ? array_search('phone', $headerRow) : ($isUpdateMode ? 4 : 3);

        $colBirthday = array_search('birthday', $headerRow) !== false ? array_search('birthday', $headerRow) : ($isUpdateMode ? 5 : 4);



        // Custom field columns: match by field key (e.g. "field_0") or by display name as fallback

        // The CSV header uses the field's display name (quoted), so we search for it.

        // We also check the key directly in case the header was generated with keys.

        $customColMap = []; // cfIdx => headerColIdx

        foreach ($customFieldDefs as $cfIdx => $cfDef) {

            $cfKey = $cfDef['key'] ?? ('field_' . $cfIdx);

            $cfNameLower = mb_strtolower(trim($cfDef['name'] ?? ''));



            // Try matching by display name first (normal flow)

            $found = false;

            foreach ($headerRow as $hIdx => $hVal) {

                $hLower = mb_strtolower(trim($hVal));

                if ($hLower === $cfNameLower || $hLower === $cfKey) {

                    $customColMap[$cfIdx] = $hIdx;

                    $found = true;

                    break;

                }

            }

            if (!$found) {

                // Positional fallback: custom fields start at col 5 (add mode) or 6 (update mode)

                $basePos = $isUpdateMode ? 6 : 5;

                $customColMap[$cfIdx] = $basePos + $cfIdx;

            }

        }



        error_log("bulkAddKids: isUpdateMode=" . ($isUpdateMode ? 'true' : 'false') . " | headerRow=" . implode('|', $headerRow));

        error_log("bulkAddKids: customFieldDefs count=" . count($customFieldDefs) . " | customColMap=" . json_encode($customColMap));



        $lineNumber = 1;



        while (($row = fgetcsv($file)) !== false) {

            $lineNumber++;

            // Pad to max needed index

            $maxIdx = max(array_merge(

                [$colClass, $colName, $colAddress, $colPhone, $colBirthday],

                $isUpdateMode ? [$colStudentId] : [],

                array_values($customColMap)

            ));

            while (count($row) <= $maxIdx)

                $row[] = '';



            $studentId = $isUpdateMode ? intval($row[$colStudentId] ?? 0) : 0;

            $classRaw = trim($row[$colClass] ?? '');

            $name = trim($row[$colName] ?? '');

            $address = trim($row[$colAddress] ?? '');

            $phone = trim($row[$colPhone] ?? '');

            $birthday = trim($row[$colBirthday] ?? '');



            // Custom field values

            $customRaws = [];

            foreach ($customFieldDefs as $cfIdx => $cfDef) {

                $customRaws[$cfIdx] = trim($row[$customColMap[$cfIdx]] ?? '');

            }



            if (empty($name)) {

                $skippedEmpty++;

                continue;

            }



            // ── Resolve class ─────────────────────────────────────

            $classId = 0;

            $className = $classRaw;

            if (!empty($classRaw)) {

                $lookupKey = mb_strtolower(trim($classRaw));

                if (isset($classMap[$lookupKey])) {

                    $classId = $classMap[$lookupKey]['id'];

                    $className = $classMap[$lookupKey]['arabic_name'];

                } else {

                    $errors[] = "السطر $lineNumber ($name): الفصل '$classRaw' غير معروف — تم التجاهل";

                    $errorCount++;

                    continue;

                }

            } elseif ($studentId > 0) {

                // In update mode, class is optional — keep existing if blank

                $existingStmt = $conn->prepare("SELECT class_id, class FROM students WHERE id = ? AND church_id = ?");

                $existingStmt->bind_param("ii", $studentId, $churchId);

                $existingStmt->execute();

                $existing = $existingStmt->get_result()->fetch_assoc();

                if ($existing) {

                    $classId = (int) $existing['class_id'];

                    $className = $existing['class'];

                }

            }



            if (!$classId && !$studentId) {

                $errors[] = "السطر $lineNumber ($name): الفصل مطلوب — تم التجاهل";

                $errorCount++;

                continue;

            }



            // ── Birthday ──────────────────────────────────────────

            $formattedBirthday = null;

            if (!empty($birthday)) {

                $formattedBirthday = formatDateToDB($birthday);

                if (!$formattedBirthday) {

                    $errors[] = "السطر $lineNumber ($name): تاريخ الميلاد '$birthday' غير صالح — تم الإضافة بدون تاريخ";

                }

            }



            // ── Phone cleaning ────────────────────────────────────

            $cleanPhone = trim($phone);

            if (preg_match('/^[\d.]+[eE][+\-]?\d+$/', $cleanPhone))

                $cleanPhone = number_format((float) $cleanPhone, 0, '.', '');

            $cleanPhone = preg_replace('/[^\d]/', '', $cleanPhone);

            if (!empty($cleanPhone)) {

                $len = strlen($cleanPhone);

                if ($len === 10 && $cleanPhone[0] === '1')

                    $cleanPhone = '0' . $cleanPhone;

                elseif ($len === 11 && substr($cleanPhone, 0, 2) !== '01')

                    $cleanPhone = '0' . substr($cleanPhone, 0, 10);

                elseif ($len < 10)

                    $cleanPhone = '';

                elseif ($len > 11)

                    $cleanPhone = substr($cleanPhone, -11);

            }

            if (!empty($cleanPhone) && !preg_match('/^01[0-9]{9}$/', $cleanPhone))

                $cleanPhone = '';



            // ── Build custom_info JSON ────────────────────────────

            $customInfoJson = null;

            if (!empty($customFieldDefs)) {

                $infoObj = [];

                foreach ($customFieldDefs as $cfIdx => $cfDef) {

                    $val = $customRaws[$cfIdx] ?? '';

                    if ($val !== '') {

                        $key = $cfDef['key'] ?? ('field_' . $cfIdx);

                        $infoObj[$key] = $val;

                    }

                }

                if (!empty($infoObj))

                    $customInfoJson = json_encode($infoObj, JSON_UNESCAPED_UNICODE);

            }



            // ══════════════════════════════════════════════════════

            // UPDATE MODE — student_id present in CSV

            // ══════════════════════════════════════════════════════

            if ($isUpdateMode && $studentId > 0) {

                // Verify the student belongs to this church

                $verifyStmt = $conn->prepare("SELECT id FROM students WHERE id = ? AND church_id = ?");

                $verifyStmt->bind_param("ii", $studentId, $churchId);

                $verifyStmt->execute();

                if (!$verifyStmt->get_result()->fetch_assoc()) {

                    $errors[] = "السطر $lineNumber ($name): ID $studentId غير موجود أو لا يخص هذه الكنيسة — تم التجاهل";

                    $errorCount++;

                    continue;

                }



                // Build UPDATE — only update fields that are non-empty in CSV

                // (so leaving a cell blank doesn't erase existing data)

                $setParts = ["name = ?", "updated_at = NOW()"];

                $setTypes = "s";

                $setValues = [$name];



                if ($classId > 0) {

                    $setParts[] = "class_id = ?";

                    $setParts[] = "class = ?";

                    $setTypes .= "is";

                    $setValues[] = $classId;

                    $setValues[] = $className;

                }

                if ($address !== '') {

                    $setParts[] = "address = ?";

                    $setTypes .= "s";

                    $setValues[] = $address;

                }

                if ($cleanPhone !== '') {

                    $setParts[] = "phone = ?";

                    $setTypes .= "s";

                    $setValues[] = $cleanPhone;

                }

                if ($formattedBirthday !== null) {

                    $setParts[] = "birthday = ?";

                    $setTypes .= "s";

                    $setValues[] = $formattedBirthday;

                }

                if ($customInfoJson !== null) {

                    $setParts[] = "custom_info = ?";

                    $setTypes .= "s";

                    $setValues[] = $customInfoJson;

                }



                $setTypes .= "ii"; // WHERE id = ? AND church_id = ?

                $setValues[] = $studentId;

                $setValues[] = $churchId;



                $updateSql = "UPDATE students SET " . implode(', ', $setParts) . " WHERE id = ? AND church_id = ?";

                $updateStmt = $conn->prepare($updateSql);

                $updateStmt->bind_param($setTypes, ...$setValues);



                if ($updateStmt->execute()) {

                    $updatedCount++;

                } else {

                    $errors[] = "السطر $lineNumber ($name): فشل في التحديث — " . $updateStmt->error;

                    $errorCount++;

                }

                continue;

            }



            // ══════════════════════════════════════════════════════

            // ADD MODE — no student_id, check for duplicates first

            // ══════════════════════════════════════════════════════

            $checkStmt = $conn->prepare("

                SELECT id FROM students

                WHERE church_id = ?

                  AND LOWER(REPLACE(name,' ','')) = LOWER(REPLACE(?,' ',''))

                  AND class_id = ?

            ");

            $checkStmt->bind_param("isi", $churchId, $name, $classId);

            $checkStmt->execute();

            if ($checkStmt->get_result()->num_rows > 0) {

                $errors[] = "السطر $lineNumber: '$name' موجود بالفعل في فصل '$className' — تم التجاهل";

                $skippedEmpty++;

                continue;

            }



            if ($formattedBirthday !== null) {

                $stmt = $conn->prepare("

                    INSERT INTO students

                    (church_id, name, class_id, class, address, phone, birthday,

                     commitment_coupons, coupons, attendance_coupons, custom_info)

                    VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 0, ?)

                ");

                // i=church_id, s=name, i=class_id, s=class, s=address, s=phone, s=birthday, s=custom_info

                $stmt->bind_param("isisssss", $churchId, $name, $classId, $className, $address, $cleanPhone, $formattedBirthday, $customInfoJson);

            } else {

                $stmt = $conn->prepare("

                    INSERT INTO students

                    (church_id, name, class_id, class, address, phone,

                     commitment_coupons, coupons, attendance_coupons, custom_info)

                    VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0, ?)

                ");

                // i=church_id, s=name, i=class_id, s=class, s=address, s=phone, s=custom_info

                $stmt->bind_param("isissss", $churchId, $name, $classId, $className, $address, $cleanPhone, $customInfoJson);

            }



            if ($stmt->execute()) {

                $addedCount++;

            } else {

                $errors[] = "السطر $lineNumber ($name): فشل في الإضافة — " . $stmt->error;

                $errorCount++;

            }

        }



        fclose($file);



        if ($addedCount === 0 && $updatedCount === 0 && $errorCount > 0) {

            $conn->rollback();

            sendJSON([

                'success' => false,

                'message' => 'لم يتم إضافة أو تحديث أي طفل. يرجى مراجعة الأخطاء.',

                'errors' => array_slice($errors, 0, 25),

                'added' => 0,

                'updated' => 0,

                'failed' => $errorCount,

                'skipped' => $skippedEmpty,

                'availableClasses' => array_column($churchClasses, 'arabic_name'),

            ]);

        } else {

            $conn->commit();

            $msg = '';

            if ($addedCount > 0)

                $msg .= "تم إضافة $addedCount طفل";

            if ($updatedCount > 0)

                $msg .= ($msg ? ' — ' : '') . "تم تحديث $updatedCount طفل";

            if ($errorCount > 0)

                $msg .= " (فشل/تجاهل $errorCount)";

            if ($skippedEmpty > 0)

                $msg .= " (صفوف فارغة: $skippedEmpty)";

            sendJSON([

                'success' => true,

                'message' => $msg,

                'addedCount' => $addedCount,

                'updatedCount' => $updatedCount,

                'failed' => $errorCount,

                'skipped' => $skippedEmpty,

                'errors' => array_slice($errors, 0, 25),

                'availableClasses' => array_column($churchClasses, 'arabic_name'),

            ]);

        }



    } catch (Exception $e) {

        if (isset($conn))

            $conn->rollback();

        error_log("bulkAddKids error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في الاستيراد: ' . $e->getMessage()]);

    }

}

function getKidsData()

{

    try {

        $churchId = getChurchId();

        $classFilter = sanitize($_GET['class'] ?? '');



        $conn = getDBConnection();



        $sql = "SELECT 

                    s.id, s.name, s.address, s.phone, s.birthday,

                    s.coupons, s.attendance_coupons, s.commitment_coupons,

                    s.image_url, s.church_id, s.class_id,

                    c.church_name,

                    cl.arabic_name as class

                FROM students s

                LEFT JOIN churches c ON s.church_id = c.id

                LEFT JOIN classes cl ON s.class_id = cl.id

                WHERE s.church_id = ?";



        $params = [$churchId];

        $types = "i";



        if (!empty($classFilter)) {

            $sql .= " AND cl.arabic_name = ?";

            $params[] = $classFilter;

            $types .= "s";

        }



        $sql .= " ORDER BY 

                CASE cl.arabic_name 

                    WHEN 'حضانة' THEN 1

                    WHEN 'أولى' THEN 2

                    WHEN 'تانية' THEN 3

                    WHEN 'تالتة' THEN 4

                    WHEN 'رابعة' THEN 5

                    WHEN 'خامسة' THEN 6

                    WHEN 'سادسة' THEN 7

                    ELSE 8

                END, s.name";



        $stmt = $conn->prepare($sql);

        $stmt->bind_param($types, ...$params);

        $stmt->execute();

        $result = $stmt->get_result();



        $kids = [];

        while ($row = $result->fetch_assoc()) {

            $kids[] = [

                'id' => $row['id'],

                'name' => $row['name'],

                'class' => $row['class'] ?? '---',

                'address' => $row['address'] ?? '',

                'phone' => $row['phone'] ?? '',

                'birthday' => formatDateFromDB($row['birthday']),

                'image_url' => $row['image_url'] ?? '',

                'coupons' => $row['coupons'] ?? 0,

                'attendance_coupons' => $row['attendance_coupons'] ?? 0,

                'commitment_coupons' => $row['commitment_coupons'] ?? 0

            ];

        }



        sendJSON(['success' => true, 'kids' => $kids, 'users' => $kids]);



    } catch (Exception $e) {

        error_log("getKidsData error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في جلب بيانات الأطفال']);

    }

}



function handleKidLogin()

{

    try {

        $usernameInput = sanitize($_POST['username'] ?? '');

        $password = $_POST['password'] ?? '';



        if (empty($usernameInput) || empty($password)) {

            sendJSON(['success' => false, 'message' => 'رقم الهاتف وكلمة المرور مطلوبان']);

        }



        $passwordHash = hash('sha256', $password);

        $cleanInput = preg_replace('/[^\d]/', '', $usernameInput);



        error_log("🔐 Login attempt with input: $cleanInput");



        $conn = getDBConnection();



        $stmt = $conn->prepare("

            SELECT

                s.id, s.name, s.address, s.phone, s.birthday, s.email,

                s.coupons, s.attendance_coupons, s.commitment_coupons,

                s.task_coupons, s.image_url, s.church_id, s.class_id,

                s.custom_info, s.trip_points,

                c.church_name,
                COALESCE(c.church_type, 'kids') AS church_type,

                COALESCE(cc.arabic_name, cl.arabic_name, s.class) AS class

            FROM students s

            LEFT JOIN churches c  ON s.church_id = c.id

            LEFT JOIN church_classes cc ON cc.id = s.class_id AND cc.church_id = s.church_id AND cc.is_active = 1

            LEFT JOIN classes cl  ON cl.id = s.class_id

            WHERE (s.phone LIKE CONCAT('%', ?) OR s.phone = ?)

            AND s.password_hash = ?

        ");

        $stmt->bind_param("sss", $cleanInput, $cleanInput, $passwordHash);

        $stmt->execute();

        $result = $stmt->get_result();



        $students = [];

        $authenticatedIds = [];

        while ($row = $result->fetch_assoc()) {

            $row['class'] = $row['class'] ?? '---';

            $students[] = $row;

            $authenticatedIds[] = $row['id'];

        }



        // Also load siblings on same phone

        if (!empty($authenticatedIds)) {

            $idList = implode(',', array_map('intval', $authenticatedIds));

            $sibStmt = $conn->prepare("

                SELECT

                    s.id, s.name, s.address, s.phone, s.birthday, s.email,

                    s.coupons, s.attendance_coupons, s.commitment_coupons,

                    s.task_coupons, s.image_url, s.church_id, s.class_id,

                    s.custom_info,

                    c.church_name,

                    COALESCE(cc.arabic_name, cl.arabic_name, s.class) AS class

                FROM students s

                LEFT JOIN churches c  ON s.church_id = c.id

                LEFT JOIN church_classes cc ON cc.id = s.class_id AND cc.church_id = s.church_id AND cc.is_active = 1

                LEFT JOIN classes cl  ON cl.id = s.class_id

                WHERE (s.phone LIKE CONCAT('%', ?) OR s.phone = ?)

                AND s.id NOT IN ($idList)

            ");

            $sibStmt->bind_param("ss", $cleanInput, $cleanInput);

            $sibStmt->execute();

            $sibResult = $sibStmt->get_result();

            while ($row = $sibResult->fetch_assoc()) {

                $row['class'] = $row['class'] ?? '---';

                $students[] = $row;

            }

        }



        if (count($students) > 0) {

            error_log("🔐 Login successful for: $cleanInput");

            sendJSON(['success' => true, 'data' => $students, 'message' => 'تم تسجيل الدخول بنجاح']);

        } else {

            error_log("🔐 Login failed for: $cleanInput");

            sendJSON(['success' => false, 'message' => 'رقم الهاتف أو كلمة المرور غير صحيحة', 'data' => []]);

        }



    } catch (Exception $e) {

        error_log("❌ handleKidLogin error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تسجيل الدخول']);

    }

}

function checkKidPasswordByPhone()

{

    try {

        $phone = sanitize($_POST['phone'] ?? '');



        if (empty($phone)) {

            sendJSON(['success' => false, 'message' => 'رقم الهاتف مطلوب']);

        }



        // Clean the phone number

        $cleanPhone = preg_replace('/[^\d]/', '', $phone);



        error_log("🔐 Checking password for phone: $cleanPhone");



        $conn = getDBConnection();



        // First check if student exists

        $checkStmt = $conn->prepare("

            SELECT id, name, phone 

            FROM students 

            WHERE phone = ? 

               OR phone LIKE CONCAT('%', ?)

               OR REPLACE(phone, '''', '') = ?

               OR REPLACE(phone, '''', '') LIKE CONCAT('%', ?)

            LIMIT 1

        ");

        $checkStmt->bind_param("ssss", $cleanPhone, $cleanPhone, $cleanPhone, $cleanPhone);

        $checkStmt->execute();

        $result = $checkStmt->get_result();



        if ($student = $result->fetch_assoc()) {

            $studentId = $student['id'];



            // Now check if password exists

            // First, check if password_hash column exists

            $columnCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'password_hash'");



            if ($columnCheck && $columnCheck->num_rows > 0) {

                // Column exists, check if there's a password

                $passwordStmt = $conn->prepare("

                    SELECT password_hash FROM students WHERE id = ?

                ");

                $passwordStmt->bind_param("i", $studentId);

                $passwordStmt->execute();

                $passwordResult = $passwordStmt->get_result();

                $passwordData = $passwordResult->fetch_assoc();



                $hasPassword = !empty($passwordData['password_hash']);

            } else {

                // Column doesn't exist or no password

                $hasPassword = false;

            }



            error_log("🔐 Student found: ID=" . $studentId . ", Name=" . $student['name'] .

                ", Has Password=" . ($hasPassword ? 'YES' : 'NO'));



            sendJSON([

                'success' => true,

                'has_password' => $hasPassword,

                'student_id' => $studentId,

                'message' => $hasPassword ? 'يوجد كلمة مرور مسجلة لهذا الرقم' : 'لا توجد كلمة مرور مسجلة'

            ]);



        } else {

            error_log("🔐 No student found for phone: $cleanPhone");

            sendJSON([

                'success' => false,

                'message' => 'لم يتم العثور على طفل بهذا الرقم'

            ]);

        }



    } catch (Exception $e) {

        error_log("❌ checkKidPasswordByPhone error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في التحقق: ' . $e->getMessage()]);

    }

}



function setupStudentPassword()

{

    try {

        $studentId = intval($_POST['studentId'] ?? 0);

        $phone = sanitize($_POST['phone'] ?? '');

        $password = $_POST['password'] ?? '';

        $applyToAllSiblings = isset($_POST['applyToAllSiblings']) && $_POST['applyToAllSiblings'] === 'true';



        if ($studentId === 0 || empty($phone) || empty($password)) {

            sendJSON(['success' => false, 'message' => 'بيانات غير كاملة']);

        }



        $passwordHash = hash('sha256', $password);

        $cleanPhone = preg_replace('/[^\d]/', '', $phone);



        $conn = getDBConnection();



        if ($applyToAllSiblings) {

            // Apply password to ALL students with this phone number

            $updateStmt = $conn->prepare("

                UPDATE students 

                SET password_hash = ?, updated_at = NOW()

                WHERE (phone LIKE CONCAT('%', ?) OR phone = ?)

            ");

            $updateStmt->bind_param("sss", $passwordHash, $cleanPhone, $cleanPhone);



            if ($updateStmt->execute()) {

                $affectedRows = $updateStmt->affected_rows;

                sendJSON([

                    'success' => true,

                    'message' => "تم حفظ كلمة المرور لـ $affectedRows حساب",

                    'updated_count' => $affectedRows

                ]);

            } else {

                sendJSON(['success' => false, 'message' => 'فشل في حفظ كلمة المرور: ' . $conn->error]);

            }

        } else {

            // Apply password only to selected student

            $updateStmt = $conn->prepare("

                UPDATE students 

                SET password_hash = ?, updated_at = NOW()

                WHERE id = ?

            ");

            $updateStmt->bind_param("si", $passwordHash, $studentId);



            if ($updateStmt->execute()) {

                sendJSON([

                    'success' => true,

                    'message' => 'تم حفظ كلمة المرور بنجاح'

                ]);

            } else {

                sendJSON(['success' => false, 'message' => 'فشل في حفظ كلمة المرور: ' . $conn->error]);

            }

        }



    } catch (Exception $e) {

        error_log("setupStudentPassword error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في إعداد كلمة المرور: ' . $e->getMessage()]);

    }

}



// ── Change/Set student password with old-password verification ───────────────

function changeStudentPassword()

{

    try {

        $studentId = intval($_POST['studentId'] ?? 0);

        $phone     = sanitize($_POST['phone'] ?? '');

        $oldPass   = $_POST['oldPassword'] ?? '';

        $newPass   = $_POST['newPassword'] ?? '';

        $isAdd     = ($_POST['isAdd'] ?? 'false') === 'true'; // true = no old pass needed



        if ($studentId === 0 || empty($phone) || empty($newPass)) {

            sendJSON(['success' => false, 'message' => 'بيانات غير كاملة']);

            return;

        }

        if (strlen($newPass) < 6) {

            sendJSON(['success' => false, 'message' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل']);

            return;

        }



        $conn = getDBConnection();



        // Fetch current hash

        $stmt = $conn->prepare("SELECT password_hash FROM students WHERE id = ? LIMIT 1");

        $stmt->bind_param("i", $studentId);

        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();

        $currentHash = $row['password_hash'] ?? '';



        if (!$isAdd) {

            // Verify old password

            if (empty($oldPass)) {

                sendJSON(['success' => false, 'message' => 'يرجى إدخال كلمة المرور الحالية']);

                return;

            }

            $oldHash = hash('sha256', $oldPass);

            if ($oldHash !== $currentHash && !password_verify($oldPass, $currentHash)) {

                sendJSON(['success' => false, 'message' => 'كلمة المرور الحالية غير صحيحة']);

                return;

            }

        } else {

            // isAdd mode — only allowed if account actually has no password yet

            if (!empty($currentHash)) {

                sendJSON(['success' => false, 'message' => 'الحساب لديه كلمة مرور بالفعل. استخدم تغيير كلمة المرور.']);

                return;

            }

        }



        $newHash = hash('sha256', $newPass);

        $upd = $conn->prepare("UPDATE students SET password_hash = ?, updated_at = NOW() WHERE id = ?");

        $upd->bind_param("si", $newHash, $studentId);



        if ($upd->execute()) {

            sendJSON(['success' => true, 'message' => $isAdd ? 'تم إضافة كلمة المرور بنجاح' : 'تم تغيير كلمة المرور بنجاح']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في الحفظ: ' . $conn->error]);

        }



    } catch (Exception $e) {

        error_log("changeStudentPassword error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);

    }

}



function kidLoginByPhoneWithPassword()

{

    try {

        $phone = sanitize($_POST['phone'] ?? '');

        $password = $_POST['password'] ?? '';

        $studentId = intval($_POST['studentId'] ?? 0);



        if (empty($phone) || empty($password)) {

            sendJSON(['success' => false, 'message' => 'رقم الهاتف وكلمة المرور مطلوبان']);

            return;

        }



        $sha256Hash = hash('sha256', $password); // legacy

        $cleanPhone = preg_replace('/[^\d]/', '', $phone);



        error_log("🔐 Phone+password login attempt: $cleanPhone, studentId: $studentId");



        $conn = getDBConnection();



        // Fetch candidates by phone

        if ($studentId > 0) {

            $stmt = $conn->prepare("

                SELECT s.id, s.name, s.address, s.phone, s.birthday,

                       s.coupons, s.attendance_coupons, s.commitment_coupons,

                       s.task_coupons, s.image_url, s.church_id, s.class_id,

                       s.password_hash, s.custom_info,

                       c.church_name,

                       COALESCE(cc.arabic_name, cl.arabic_name, s.class) AS class

                FROM students s

                LEFT JOIN churches c  ON s.church_id = c.id

                LEFT JOIN church_classes cc ON cc.id = s.class_id AND cc.church_id = s.church_id

                LEFT JOIN classes cl  ON cl.id = s.class_id

                WHERE s.id = ?

            ");

            $stmt->bind_param("i", $studentId);

        } else {

            $stmt = $conn->prepare("

                SELECT s.id, s.name, s.address, s.phone, s.birthday,

                       s.coupons, s.attendance_coupons, s.commitment_coupons,

                       s.task_coupons, s.image_url, s.church_id, s.class_id,

                       s.password_hash, s.custom_info,

                       c.church_name,

                       COALESCE(cc.arabic_name, cl.arabic_name, s.class) AS class

                FROM students s

                LEFT JOIN churches c  ON s.church_id = c.id

                LEFT JOIN church_classes cc ON cc.id = s.class_id AND cc.church_id = s.church_id

                LEFT JOIN classes cl  ON cl.id = s.class_id

                WHERE (s.phone = ? OR s.phone LIKE CONCAT('%', ?))

            ");

            $stmt->bind_param("ss", $cleanPhone, $cleanPhone);

        }

        $stmt->execute();

        $result = $stmt->get_result();



        $students = [];

        while ($row = $result->fetch_assoc()) {

            $storedHash = $row['password_hash'] ?? '';

            $matched = !empty($storedHash) && (

                password_verify($password, $storedHash) || $storedHash === $sha256Hash

            );

            if ($matched) {

                $row['birthday'] = formatDateFromDB($row['birthday']);

                $row['class'] = $row['class'] ?? '---';

                unset($row['password_hash']);

                $students[] = $row;

            }

        }



        if (count($students) > 0) {

            sendJSON(['success' => true, 'data' => $students, 'message' => 'تم تسجيل الدخول بنجاح']);

        } else {

            sendJSON(['success' => false, 'message' => 'كلمة المرور غير صحيحة', 'data' => []]);

        }



    } catch (Exception $e) {

        error_log("❌ kidLoginByPhoneWithPassword error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تسجيل الدخول']);

    }

}

function kidLogin()

{

    try {

        $usernameInput = sanitize($_POST['username'] ?? '');

        $password = $_POST['password'] ?? '';



        if (empty($usernameInput) || empty($password)) {

            sendJSON(['success' => false, 'message' => 'اسم المستخدم وكلمة المرور مطلوبان']);

            return;

        }



        $conn = getDBConnection();



        // Detect if input looks like a phone number (digits only)

        $cleanInput = preg_replace('/[^\d]/', '', $usernameInput);

        $isPhoneLike = strlen($cleanInput) >= 7;

        $sha256Hash = hash('sha256', $password); // legacy hash



        error_log("🔐 [kidLogin] input: '$usernameInput' | isPhone: " . ($isPhoneLike ? 'yes' : 'no'));



        // ── Build candidate list ─────────────────────────────────

        // We search by phone OR by username stored in custom_info JSON

        $candidates = [];



        // 1. Search by phone (digits)

        if ($isPhoneLike) {

            $stmt = $conn->prepare("

                SELECT s.id, s.name, s.address, s.phone, s.birthday, s.email,

                       s.coupons, s.attendance_coupons, s.commitment_coupons,

                       s.task_coupons, s.image_url, s.church_id, s.class_id,

                       s.custom_info, s.password_hash,

                       c.church_name,

                       COALESCE(cc.arabic_name, cl.arabic_name, s.class) AS class

                FROM students s

                LEFT JOIN churches c  ON s.church_id = c.id

                LEFT JOIN church_classes cc ON cc.id = s.class_id AND cc.church_id = s.church_id

                LEFT JOIN classes cl  ON cl.id = s.class_id

                WHERE (s.phone LIKE CONCAT('%', ?) OR s.phone = ?)

            ");

            $stmt->bind_param("ss", $cleanInput, $cleanInput);

            $stmt->execute();

            $res = $stmt->get_result();

            while ($row = $res->fetch_assoc()) {

                $row['class'] = $row['class'] ?? '---';

                $candidates[$row['id']] = $row;

            }

        }



        // 2. Search by username in custom_info JSON

        // custom_info is {"username":"..."} stored as JSON text

        $usernameClean = trim($usernameInput);

        if (!empty($usernameClean)) {

            $stmt2 = $conn->prepare("

                SELECT s.id, s.name, s.address, s.phone, s.birthday, s.email,

                       s.coupons, s.attendance_coupons, s.commitment_coupons,

                       s.task_coupons, s.image_url, s.church_id, s.class_id,

                       s.custom_info, s.password_hash,

                       c.church_name,

                       COALESCE(cc.arabic_name, cl.arabic_name, s.class) AS class

                FROM students s

                LEFT JOIN churches c  ON s.church_id = c.id

                LEFT JOIN church_classes cc ON cc.id = s.class_id AND cc.church_id = s.church_id

                LEFT JOIN classes cl  ON cl.id = s.class_id

                WHERE JSON_UNQUOTE(JSON_EXTRACT(s.custom_info, '$.username')) = ?

                   OR JSON_UNQUOTE(JSON_EXTRACT(s.custom_info, '$.username')) = ?

            ");

            $usernameCleanLower = strtolower($usernameClean);

            $stmt2->bind_param("ss", $usernameClean, $usernameCleanLower);

            $stmt2->execute();

            $res2 = $stmt2->get_result();

            while ($row = $res2->fetch_assoc()) {

                $row['class'] = $row['class'] ?? '---';

                if (!isset($candidates[$row['id']])) {

                    $candidates[$row['id']] = $row;

                }

            }

        }



        error_log("🔐 [kidLogin] candidates before auth: " . count($candidates));



        // ── Verify password against each candidate ───────────────

        $authenticated = [];

        foreach ($candidates as $student) {

            $storedHash = $student['password_hash'] ?? '';

            $matched = false;

            $needsHashUpgrade = false;



            if (!empty($storedHash)) {

                // Try SHA256 first (standard for all flows)

                if ($storedHash === $sha256Hash) {

                    $matched = true;

                }

                // Fallback: bcrypt (old registrations) — migrate to SHA256 on success

                elseif (password_verify($password, $storedHash)) {

                    $matched = true;

                    $needsHashUpgrade = true;

                }

            }



            if ($matched) {

                // Migrate bcrypt → SHA256 so all hashes are consistent

                if ($needsHashUpgrade) {

                    $upd = $conn->prepare("UPDATE students SET password_hash = ? WHERE id = ?");

                    if ($upd) {

                        $studentId = $student['id'];

                        $upd->bind_param("si", $sha256Hash, $studentId);

                        $upd->execute();

                    }

                    error_log("🔄 [kidLogin] Migrated bcrypt→SHA256 for student ID " . $student['id']);

                }

                $student['has_password'] = !empty($storedHash); // tell the frontend
                unset($student['password_hash']); // never expose hash

                $authenticated[] = $student;

            }

        }



        error_log("🔐 [kidLogin] authenticated: " . count($authenticated));



        if (count($authenticated) > 0) {

            $vals = array_values($authenticated);
            sendJSON([

                'success' => true,

                'data' => $vals,
                'users' => $vals,
                'user' => count($vals) === 1 ? $vals[0] : null,

                'message' => count($vals) > 1

                    ? 'تم تسجيل الدخول بنجاح - ' . count($vals) . ' أطفال مرتبطين'

                    : 'تم تسجيل الدخول بنجاح'

            ]);

        } else {

            sendJSON([

                'success' => false,

                'message' => 'اسم المستخدم أو رقم الهاتف أو كلمة المرور غير صحيحة',

                'data' => []

            ]);

        }



    } catch (Exception $e) {

        error_log("❌ [kidLogin] Error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تسجيل الدخول: ' . $e->getMessage()]);

    }

}

function checkUsernameAvailable()

{

    try {

        $username = trim(sanitize($_POST['username'] ?? ''));

        if (empty($username)) {

            sendJSON(['available' => false, 'message' => 'اسم المستخدم مطلوب']);

            return;

        }

        if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {

            sendJSON(['available' => false, 'message' => 'حروف إنجليزية وأرقام وشرطة سفلية فقط (3-30)']);

            return;

        }

        $conn = getDBConnection();



        // Check students table — username stored in custom_info JSON

        $s1 = $conn->prepare("SELECT id FROM students WHERE JSON_UNQUOTE(JSON_EXTRACT(custom_info, '$.username')) = ? LIMIT 1");

        if ($s1) {

            $s1->bind_param("s", $username);

            $s1->execute();

            if ($s1->get_result()->num_rows > 0) {

                sendJSON(['available' => false, 'message' => 'اسم المستخدم محجوز']);

                return;

            }

        }



        // Check pending_registrations

        $conn->query("ALTER TABLE pending_registrations ADD COLUMN IF NOT EXISTS username VARCHAR(100) DEFAULT NULL");

        $s2 = $conn->prepare("SELECT id FROM pending_registrations WHERE username = ? AND status = 'pending' LIMIT 1");

        if ($s2) {

            $s2->bind_param("s", $username);

            $s2->execute();

            if ($s2->get_result()->num_rows > 0) {

                sendJSON(['available' => false, 'message' => 'اسم المستخدم محجوز']);

                return;

            }

        }



        sendJSON(['available' => true, 'message' => 'اسم المستخدم متاح']);

    } catch (Exception $e) {

        sendJSON(['available' => false, 'message' => 'خطأ: ' . $e->getMessage()]);

    }

}



function getStudentProfile()

{

    try {

        $studentId = intval($_POST['studentId'] ?? $_GET['studentId'] ?? 0);



        if ($studentId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف الطفل مطلوب']);

            return;

        }



        $conn = getDBConnection();



        $stmt = $conn->prepare("

            SELECT

                s.id, s.name, s.address, s.phone, s.birthday, s.email,

                s.coupons, s.attendance_coupons, s.commitment_coupons,

                s.task_coupons, s.image_url, s.church_id, s.class_id,

                s.custom_info, s.gender, s.emergency_phone, s.medical_notes,

                c.church_name,

                COALESCE(cc.arabic_name, cl.arabic_name, s.class) AS class

            FROM students s

            LEFT JOIN churches c  ON s.church_id = c.id

            LEFT JOIN church_classes cc ON cc.id = s.class_id AND cc.church_id = s.church_id

            LEFT JOIN classes cl  ON cl.id = s.class_id

            WHERE s.id = ?

        ");

        $stmt->bind_param("i", $studentId);

        $stmt->execute();

        $result = $stmt->get_result();



        if ($row = $result->fetch_assoc()) {

            // تنسيق تاريخ الميلاد

            $row['birthday'] = formatDateFromDB($row['birthday']);

            $row['class'] = $row['class'] ?? '---';



            sendJSON([

                'success' => true,

                'student' => $row,

                'user' => $row,

                'message' => 'تم تحميل الملف الشخصي'

            ]);

        } else {

            sendJSON([

                'success' => false,

                'message' => 'لم يتم العثور على الطفل'

            ]);

        }



    } catch (Exception $e) {

        error_log("getStudentProfile error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحميل الملف الشخصي']);

    }

}

// ===== SEARCH KIDS BY NAME =====

function searchKidsByName()

{

    try {

        $query = sanitize($_POST['query'] ?? $_GET['query'] ?? '');

        $churchId = intval($_POST['church_id'] ?? $_GET['church_id'] ?? 0);



        if (empty($query)) {

            sendJSON(['success' => false, 'message' => 'أدخل كلمة للبحث']);

        }



        $conn = getDBConnection();



        $searchTerm = "%{$query}%";



        // Build query — always filter to a specific church so kids only see classmates

        if ($churchId > 0) {

            $stmt = $conn->prepare("

                SELECT

                    s.id, s.name, s.coupons, s.image_url, s.church_id, s.class_id,

                    c.church_name,

                    COALESCE(cc.arabic_name, cl.arabic_name, s.class) AS class

                FROM students s

                LEFT JOIN churches c        ON s.church_id = c.id

                LEFT JOIN church_classes cc ON cc.id = s.class_id AND cc.church_id = s.church_id AND cc.is_active = 1

                LEFT JOIN classes cl        ON cl.id = s.class_id

                WHERE s.church_id = ? AND s.name LIKE ?

                ORDER BY s.name

                LIMIT 20

            ");

            $stmt->bind_param("is", $churchId, $searchTerm);

        } else {

            $stmt = $conn->prepare("

                SELECT

                    s.id, s.name, s.coupons, s.image_url, s.church_id, s.class_id,

                    c.church_name,

                    COALESCE(cc.arabic_name, cl.arabic_name, s.class) AS class

                FROM students s

                LEFT JOIN churches c        ON s.church_id = c.id

                LEFT JOIN church_classes cc ON cc.id = s.class_id AND cc.church_id = s.church_id AND cc.is_active = 1

                LEFT JOIN classes cl        ON cl.id = s.class_id

                WHERE s.name LIKE ?

                ORDER BY s.name

                LIMIT 20

            ");

            $stmt->bind_param("s", $searchTerm);

        }



        $stmt->execute();

        $result = $stmt->get_result();



        $kids = [];

        while ($row = $result->fetch_assoc()) {

            $kids[] = [

                'id' => (int) $row['id'],

                'name' => $row['name'],

                'coupons' => (int) $row['coupons'],

                'image_url' => $row['image_url'] ?? '',

                'church_id' => (int) $row['church_id'],

                'church_name' => $row['church_name'] ?? '',

                'class' => $row['class'] ?? '—',

            ];

        }



        sendJSON(['success' => true, 'kids' => $kids, 'users' => $kids, 'count' => count($kids)]);



    } catch (Exception $e) {

        error_log("searchKidsByName error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في البحث']);

    }

}

function updateStudentAttendance()

{

    checkAuth(); // Check if user is logged in



    try {

        $uncleId = $_SESSION['uncle_id'] ?? null;

        $studentId = intval($_POST['studentId'] ?? 0);

        $date = sanitize($_POST['date'] ?? '');

        $status = sanitize($_POST['status'] ?? 'present');



        if ($studentId === 0 || empty($date)) {

            sendJSON(['success' => false, 'message' => 'بيانات غير كاملة']);

            return;

        }



        // Validate status

        if (!in_array($status, ['present', 'absent'])) {

            sendJSON(['success' => false, 'message' => 'حالة الحضور غير صالحة']);

            return;

        }



        $conn = getDBConnection();



        // Check if attendance record exists

        $checkStmt = $conn->prepare("

            SELECT id, status 

            FROM attendance 

            WHERE student_id = ? AND attendance_date = ?

        ");

        $checkStmt->bind_param("is", $studentId, $date);

        $checkStmt->execute();

        $result = $checkStmt->get_result();



        if ($existing = $result->fetch_assoc()) {

            // Update existing

            $updateStmt = $conn->prepare("

                UPDATE attendance 

                SET status = ?, uncle_id = ?, updated_at = NOW()

                WHERE id = ?

            ");

            $updateStmt->bind_param("sii", $status, $uncleId, $existing['id']);

        } else {

            // Insert new - need to get church_id from student

            $studentStmt = $conn->prepare("SELECT church_id FROM students WHERE id = ?");

            $studentStmt->bind_param("i", $studentId);

            $studentStmt->execute();

            $studentResult = $studentStmt->get_result();



            if ($student = $studentResult->fetch_assoc()) {

                $churchId = $student['church_id'];



                $updateStmt = $conn->prepare("

                    INSERT INTO attendance (student_id, church_id, attendance_date, status, uncle_id)

                    VALUES (?, ?, ?, ?, ?)

                ");

                $updateStmt->bind_param("iissi", $studentId, $churchId, $date, $status, $uncleId);

            } else {

                sendJSON(['success' => false, 'message' => 'لم يتم العثور على الطفل']);

                return;

            }

        }



        if ($updateStmt->execute()) {

            sendJSON(['success' => true, 'message' => 'تم تحديث الحضور بنجاح']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في تحديث الحضور']);

        }



    } catch (Exception $e) {

        error_log("updateStudentAttendance error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحديث الحضور']);

    }

}

// ===== CHECK STUDENT PASSWORD =====

function checkStudentPassword()

{

    try {

        $studentId = intval($_POST['studentId'] ?? 0);



        if ($studentId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف الطفل مطلوب']);

            return;

        }



        $conn = getDBConnection();



        // Check if password_hash column exists

        $columnCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'password_hash'");



        if (!$columnCheck || $columnCheck->num_rows === 0) {

            sendJSON(['success' => true, 'has_password' => false]);

            return;

        }



        // Check if this specific student has password

        $stmt = $conn->prepare("SELECT password_hash FROM students WHERE id = ?");

        $stmt->bind_param("i", $studentId);

        $stmt->execute();

        $result = $stmt->get_result();

        $data = $result->fetch_assoc();



        $hasPassword = !empty($data['password_hash']);



        sendJSON([

            'success' => true,

            'has_password' => $hasPassword,

            'student_id' => $studentId

        ]);



    } catch (Exception $e) {

        error_log("checkStudentPassword error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في التحقق من كلمة المرور']);

    }

}



// ===== UPDATE COUPONS =====

function updateCouponsKids()

{

    checkAuth(); // Check if user is logged in



    try {

        $uncleId = $_SESSION['uncle_id'] ?? null;

        $studentId = intval($_POST['studentId'] ?? 0);

        $coupons = max(0, intval($_POST['coupons'] ?? 0));



        if ($studentId === 0) {

            sendJSON(['success' => false, 'message' => 'بيانات غير كاملة']);

            return;

        }



        // Only uncles can update coupons

        if (!$uncleId) {

            sendJSON(['success' => false, 'message' => 'غير مصرح - فقط للأعمام']);

            return;

        }



        $conn = getDBConnection();



        // Get current coupons to calculate attendance_coupons

        $currentStmt = $conn->prepare("

            SELECT name, coupons, attendance_coupons, commitment_coupons, task_coupons

            FROM students 

            WHERE id = ?

        ");

        $currentStmt->bind_param("i", $studentId);

        $currentStmt->execute();

        $result = $currentStmt->get_result();



        if ($student = $result->fetch_assoc()) {

            $currentAttendance = intval($student['attendance_coupons']);

            $currentTask = intval($student['task_coupons'] ?? 0);



            // Keep task coupons intact when a total coupon value is edited directly.

            $newCommitment = $coupons - $currentAttendance - $currentTask;

            if ($newCommitment < 0)

                $newCommitment = 0;



            // Update student

            $updateStmt = $conn->prepare("

                UPDATE students 

                SET coupons = ?, commitment_coupons = ?, updated_at = NOW()

                WHERE id = ?

            ");

            $updateStmt->bind_param("iii", $coupons, $newCommitment, $studentId);



            if ($updateStmt->execute()) {

                // Log the coupon change

                $logStmt = $conn->prepare("

                    INSERT INTO coupon_logs (student_id, uncle_id, old_count, new_count, change_amount, change_type)

                    VALUES (?, ?, ?, ?, ?, 'manual')

                ");

                $oldCount = intval($student['coupons']);

                $changeAmount = $coupons - $oldCount;

                $logStmt->bind_param("iiiii", $studentId, $uncleId, $oldCount, $coupons, $changeAmount);

                $logStmt->execute();



                // ► AUDIT

                auditCouponChange($studentId, $student['name'] ?? '', $oldCount, $coupons, 'تعديل يدوي');



                sendJSON(['success' => true, 'message' => 'تم تحديث الكوبونات بنجاح']);

            } else {

                sendJSON(['success' => false, 'message' => 'فشل في تحديث الكوبونات']);

            }

        } else {

            sendJSON(['success' => false, 'message' => 'لم يتم العثور على الطفل']);

        }



    } catch (Exception $e) {

        error_log("updateCoupons error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحديث الكوبونات']);

    }

}

function formatDateFromDB($dbDate)

{

    if (empty($dbDate) || $dbDate === '0000-00-00') {

        return '';

    }



    try {

        $date = DateTime::createFromFormat('Y-m-d', $dbDate);

        return $date ? $date->format('d/m/Y') : '';

    } catch (Exception $e) {

        error_log("Date format error: $dbDate - " . $e->getMessage());

        return $dbDate;

    }

}



// دالة لتحويل التاريخ من المدخلات إلى تنسيق قاعدة البيانات

function formatDateToDB($inputDate)

{

    if (empty($inputDate)) {

        return null;

    }



    // تنظيف التاريخ

    $inputDate = trim($inputDate);



    // تحويل الأرقام العربية إلى إنجليزية إذا لزم الأمر

    $arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    $englishNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    $inputDate = str_replace($arabicNumbers, $englishNumbers, $inputDate);



    // محاولة تحليل تنسيقات التاريخ المختلفة

    $formats = [

        'd/m/Y', // 31/12/2024

        'd-m-Y', // 31-12-2024

        'Y-m-d', // 2024-12-31 (مباشرة من قاعدة البيانات)

        'd/m/y', // 31/12/24

        'd-m-y', // 31-12-24

        'Y/m/d', // 2024/12/31

    ];



    foreach ($formats as $format) {

        $date = DateTime::createFromFormat($format, $inputDate);

        if ($date && $date->format($format) === $inputDate) {

            return $date->format('Y-m-d'); // تنسيق قاعدة البيانات

        }

    }



    // إذا فشلت جميع المحاولات، إرجاع القيمة الأصلية مع تحذير

    error_log("Warning: Could not parse date: $inputDate");

    return $inputDate;

}





function hasTempAttendance()

{

    $churchId = getChurchId();

    $className = sanitize($_GET['className'] ?? '');

    $date = sanitize($_GET['date'] ?? date('d/m/Y'));



    if (!$churchId || !$className) {

        sendJSON([

            'success' => false,

            'message' => 'بيانات غير كاملة'

        ]);

        return;

    }





    sendJSON([

        'success' => true,

        'has_temp' => true, // Or false based on actual check

        'message' => 'تم التحقق من البيانات المؤقتة'

    ]);

}

// ===== GET CHURCH STATISTICS =====

function getChurchStatistics()

{

    try {

        $churchId = getChurchId();

        $isAll = (!empty($_POST['all_churches']) && $_POST['all_churches'] === '1') || $churchId === 0;

        $conn = getDBConnection();



        error_log("getChurchStatistics - churchId: $churchId, isAll: " . ($isAll ? 'true' : 'false'));



        if ($isAll) {

            // Developer view - all churches combined

            $total_students = $conn->query("SELECT COUNT(*) as c FROM students")->fetch_assoc()['c'];

            $total_uncles = $conn->query("SELECT COUNT(*) as c FROM uncles WHERE deleted = 0")->fetch_assoc()['c'];

            $total_classes = $conn->query("SELECT COUNT(DISTINCT class_id) as c FROM students")->fetch_assoc()['c'];



            $pendingStmt = $conn->query("SELECT COUNT(*) as c FROM pending_registrations WHERE status='pending'");

            $pending = $pendingStmt->fetch_assoc()['c'];



            // Recent activity across all churches

            $actStmt = $conn->query("

                SELECT a.attendance_date, a.status, 

                       s.name as student_name,

                       COALESCE(cc.arabic_name, gc.arabic_name, s.class) as class,

                       c.church_name

                FROM attendance a

                JOIN students s ON a.student_id = s.id

                LEFT JOIN church_classes cc ON s.class_id = cc.id AND cc.church_id = s.church_id

                LEFT JOIN classes gc ON s.class_id = gc.id

                LEFT JOIN churches c ON s.church_id = c.id

                ORDER BY a.attendance_date DESC LIMIT 20

            ");



            $recentActivity = [];

            while ($row = $actStmt->fetch_assoc()) {

                $recentActivity[] = [

                    'date' => formatDateFromDB($row['attendance_date']),

                    'student' => $row['student_name'] . ' (' . ($row['church_name'] ?? '') . ')',

                    'class' => $row['class'] ?? 'بدون فصل',

                    'status' => $row['status'] === 'present' ? 'حاضر' : 'غائب'

                ];

            }



            // Class distribution across all churches

            $distStmt = $conn->query("

                SELECT COALESCE(cc.arabic_name, gc.arabic_name, s.class) as class,

                       COUNT(*) as count

                FROM students s

                LEFT JOIN church_classes cc ON s.class_id = cc.id AND cc.church_id = s.church_id

                LEFT JOIN classes gc ON s.class_id = gc.id

                GROUP BY class 

                ORDER BY count DESC

                LIMIT 10

            ");



            $classDistribution = [];

            while ($row = $distStmt->fetch_assoc()) {

                $classDistribution[] = [

                    'class' => $row['class'] ?? 'بدون فصل',

                    'count' => (int) $row['count']

                ];

            }

        } else {

            // Single church view (regular admin)



            // Total students

            $s1 = $conn->prepare("SELECT COUNT(*) as c FROM students WHERE church_id = ?");

            $s1->bind_param("i", $churchId);

            $s1->execute();

            $total_students = $s1->get_result()->fetch_assoc()['c'];



            // Total uncles (not deleted)

            $s2 = $conn->prepare("SELECT COUNT(*) as c FROM uncles WHERE church_id = ? AND deleted = 0");

            $s2->bind_param("i", $churchId);

            $s2->execute();

            $total_uncles = $s2->get_result()->fetch_assoc()['c'];



            // Total distinct classes

            $s3 = $conn->prepare("SELECT COUNT(DISTINCT class_id) as c FROM students WHERE church_id = ?");

            $s3->bind_param("i", $churchId);

            $s3->execute();

            $total_classes = $s3->get_result()->fetch_assoc()['c'];



            // Pending registrations

            $s4 = $conn->prepare("SELECT COUNT(*) as c FROM pending_registrations WHERE church_id = ? AND status='pending'");

            $s4->bind_param("i", $churchId);

            $s4->execute();

            $pending = $s4->get_result()->fetch_assoc()['c'];



            // Recent activity for this church

            $actStmt = $conn->prepare("

                SELECT a.attendance_date, a.status, 

                       s.name as student_name,

                       COALESCE(cc.arabic_name, gc.arabic_name, s.class) as class

                FROM attendance a

                JOIN students s ON a.student_id = s.id

                LEFT JOIN church_classes cc ON s.class_id = cc.id AND cc.church_id = s.church_id

                LEFT JOIN classes gc ON s.class_id = gc.id

                WHERE a.church_id = ?

                ORDER BY a.attendance_date DESC 

                LIMIT 10

            ");

            $actStmt->bind_param("i", $churchId);

            $actStmt->execute();



            $recentActivity = [];

            $actResult = $actStmt->get_result();

            while ($row = $actResult->fetch_assoc()) {

                $recentActivity[] = [

                    'date' => formatDateFromDB($row['attendance_date']),

                    'student' => $row['student_name'],

                    'class' => $row['class'] ?? 'بدون فصل',

                    'status' => $row['status'] === 'present' ? 'حاضر' : 'غائب'

                ];

            }



            // Class distribution for this church

            $distStmt = $conn->prepare("

                SELECT COALESCE(cc.arabic_name, gc.arabic_name, s.class) as class,

                       COUNT(*) as count

                FROM students s

                LEFT JOIN church_classes cc ON s.class_id = cc.id AND cc.church_id = s.church_id

                LEFT JOIN classes gc ON s.class_id = gc.id

                WHERE s.church_id = ?

                GROUP BY s.class_id 

                ORDER BY count DESC

            ");

            $distStmt->bind_param("i", $churchId);

            $distStmt->execute();



            $classDistribution = [];

            $distResult = $distStmt->get_result();

            while ($row = $distResult->fetch_assoc()) {

                $classDistribution[] = [

                    'class' => $row['class'] ?? 'بدون فصل',

                    'count' => (int) $row['count']

                ];

            }

        }



        sendJSON([

            'success' => true,

            'stats' => [

                'totalStudents' => intval($total_students ?? 0),

                'totalUncles' => intval($total_uncles ?? 0),

                'totalClasses' => intval($total_classes ?? 0),

                'pendingRequests' => intval($pending ?? 0),

                'recentActivity' => $recentActivity ?? [],

                'classDistribution' => $classDistribution ?? []

            ]

        ]);



    } catch (Exception $e) {

        error_log("getChurchStatistics error: " . $e->getMessage());

        error_log("Stack trace: " . $e->getTraceAsString());

        sendJSON([

            'success' => false,

            'message' => 'خطأ في جلب الإحصائيات: ' . $e->getMessage()

        ]);

    }

}

// ===== GET CLASS DETAILS =====

function getClassDetails()

{

    try {

        $churchId = getChurchId();

        $className = sanitize($_POST['class'] ?? '');



        if (empty($className)) {

            sendJSON(['success' => false, 'message' => 'اسم الفصل مطلوب']);

            return;

        }



        $conn = getDBConnection();



        $stmt = $conn->prepare("

            SELECT 

                s.id, s.name, s.phone, s.address, 

                s.birthday, s.coupons, s.attendance_coupons, 

                s.commitment_coupons, s.image_url,

                DATE_FORMAT(s.created_at, '%d/%m/%Y') as joined_date

            FROM students s

            JOIN classes cl ON s.class_id = cl.id

            WHERE s.church_id = ? AND cl.arabic_name = ?

            ORDER BY s.name

        ");

        $stmt->bind_param("is", $churchId, $className);

        $stmt->execute();

        $result = $stmt->get_result();



        $students = [];

        while ($row = $result->fetch_assoc()) {

            $students[] = [

                'id' => $row['id'],

                'name' => $row['name'],

                'phone' => $row['phone'] ?? '',

                'address' => $row['address'] ?? '',

                'birthday' => formatDateFromDB($row['birthday']),

                'coupons' => $row['coupons'],

                'attendance_coupons' => $row['attendance_coupons'],

                'commitment_coupons' => $row['commitment_coupons'],

                'image_url' => $row['image_url'] ?? '',

                'joined_date' => $row['joined_date']

            ];

        }



        // Get attendance summary for this class

        $attStmt = $conn->prepare("

            SELECT 

                COUNT(DISTINCT attendance_date) as total_days,

                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as total_present

            FROM attendance a

            JOIN students s ON a.student_id = s.id

            JOIN classes cl ON s.class_id = cl.id

            WHERE a.church_id = ? AND cl.arabic_name = ?

        ");

        $attStmt->bind_param("is", $churchId, $className);

        $attStmt->execute();

        $attResult = $attStmt->get_result();

        $attData = $attResult->fetch_assoc();



        sendJSON([

            'success' => true,

            'students' => $students,

            'stats' => [

                'total_students' => count($students),

                'total_days' => intval($attData['total_days'] ?? 0),

                'total_present' => intval($attData['total_present'] ?? 0)

            ]

        ]);



    } catch (Exception $e) {

        error_log("getClassDetails error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في جلب تفاصيل الفصل']);

    }

}



// ===== GET STUDENT ATTENDANCE DETAILS =====

function getStudentAttendanceDetails()

{

    try {

        $studentId = intval($_POST['studentId'] ?? 0);



        if ($studentId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف الطفل مطلوب']);

            return;

        }



        $conn = getDBConnection();



        $stmt = $conn->prepare("

            SELECT 

                attendance_date,

                status,

                DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as recorded_time

            FROM attendance

            WHERE student_id = ?

            ORDER BY attendance_date DESC

        ");

        $stmt->bind_param("i", $studentId);

        $stmt->execute();

        $result = $stmt->get_result();



        $attendance = [];

        $presentCount = 0;

        $absentCount = 0;



        while ($row = $result->fetch_assoc()) {

            $attendance[] = [

                'date' => formatDateFromDB($row['attendance_date']),

                'status' => $row['status'] === 'present' ? 'حاضر' : 'غائب',

                'status_code' => $row['status'],

                'recorded_time' => $row['recorded_time']

            ];



            if ($row['status'] === 'present') {

                $presentCount++;

            } else {

                $absentCount++;

            }

        }



        sendJSON([

            'success' => true,

            'attendance' => $attendance,

            'stats' => [

                'total' => count($attendance),

                'present' => $presentCount,

                'absent' => $absentCount,

                'attendance_rate' => count($attendance) > 0 ? round(($presentCount / count($attendance)) * 100) : 0

            ]

        ]);



    } catch (Exception $e) {

        error_log("getStudentAttendanceDetails error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في جلب تفاصيل الحضور']);

    }

}



function updateCouponsWithReason()

{

    checkAuth();



    try {

        $studentId = intval($_POST['studentId'] ?? 0);

        $coupons = max(0, intval($_POST['coupons'] ?? 0));

        $reason = sanitize($_POST['reason'] ?? 'تعديل يدوي');

        $uncleId = $_SESSION['uncle_id'] ?? null;



        if ($studentId === 0) {

            sendJSON(['success' => false, 'message' => 'بيانات غير كاملة']);

            return;

        }



        $conn = getDBConnection();



        // BEFORE update, get old values

        $beforeStmt = $conn->prepare("SELECT name, coupons, attendance_coupons, task_coupons FROM students WHERE id = ?");

        $beforeStmt->bind_param("i", $studentId);

        $beforeStmt->execute();

        $beforeRow = $beforeStmt->get_result()->fetch_assoc();

        $oldTotal = intval($beforeRow['coupons'] ?? 0);

        $sName = $beforeRow['name'] ?? '';

        $currentAttendance = intval($beforeRow['attendance_coupons'] ?? 0);

        $currentTask = intval($beforeRow['task_coupons'] ?? 0);



        $newCommitment = max(0, $coupons - $currentAttendance - $currentTask);



        // Update student

        $updateStmt = $conn->prepare("UPDATE students SET coupons = ?, commitment_coupons = ?, updated_at = NOW() WHERE id = ?");

        $updateStmt->bind_param("iii", $coupons, $newCommitment, $studentId);



        if ($updateStmt->execute()) {

            // Log the coupon change

            $logStmt = $conn->prepare("

                INSERT INTO coupon_logs (student_id, uncle_id, old_count, new_count, change_amount, change_type, reason)

                VALUES (?, ?, ?, ?, ?, 'manual', ?)

            ");

            $changeAmount = $coupons - $oldTotal;

            $logStmt->bind_param("iiiiis", $studentId, $uncleId, $oldTotal, $coupons, $changeAmount, $reason);

            $logStmt->execute();



            // ► AUDIT

            auditCouponChange($studentId, $sName, $oldTotal, $coupons, $reason ?? 'تعديل يدوي');



            sendJSON(['success' => true, 'message' => 'تم تحديث الكوبونات بنجاح']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في تحديث الكوبونات']);

        }



    } catch (Exception $e) {

        error_log("updateCouponsWithReason error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحديث الكوبونات']);

    }

}

function getClassesForChurch(int $churchId): array

{

    $conn = getDBConnection();

    ensureChurchClassesOrderColumn($conn);



    // Check custom classes first

    $stmt = $conn->prepare("

        SELECT cc.id, cc.code, cc.arabic_name, cc.display_order,

               COALESCE(NULLIF(cc.`order`, 0), cc.display_order) AS class_order,

               cc.color, cc.icon,

               COUNT(s.id) AS student_count

        FROM   church_classes cc

        LEFT JOIN students s ON s.class_id = cc.id AND s.church_id = ?

        WHERE  cc.church_id = ? AND cc.is_active = 1

        GROUP  BY cc.id

        ORDER  BY class_order, cc.arabic_name

    ");

    $stmt->bind_param("ii", $churchId, $churchId);

    $stmt->execute();

    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);



    if (count($rows) > 0) {

        foreach ($rows as &$r) {

            $r['is_custom'] = true;

            $r['student_count'] = (int) $r['student_count'];

            $r['color'] = $r['color'] ?? '#4f46e5';

            $r['icon'] = $r['icon'] ?? '';

        }

        return $rows;

    }



    // Fall back to global classes

    $stmt2 = $conn->prepare("

        SELECT c.id, c.code, c.arabic_name, c.display_order,

               c.color, '' AS icon,

               COUNT(s.id) AS student_count

        FROM   classes c

        LEFT JOIN students s ON s.class_id = c.id AND s.church_id = ?

        GROUP  BY c.id

        ORDER  BY c.display_order

    ");

    $stmt2->bind_param("i", $churchId);

    $stmt2->execute();

    $rows2 = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($rows2 as &$r) {

        $r['is_custom'] = false;

        $r['student_count'] = (int) $r['student_count'];

        $r['color'] = $r['color'] ?? '#4f46e5';

        $r['icon'] = $r['icon'] ?? '';

    }

    return $rows2;

}

// ── GET classes for the authenticated church ─────────────────

function getChurchClasses()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $classes = getClassesForChurch($churchId);



        // Also return whether custom classes exist

        $conn = getDBConnection();

        $chk = $conn->prepare("SELECT COUNT(*) AS cnt FROM church_classes WHERE church_id = ?");

        $chk->bind_param("i", $churchId);

        $chk->execute();

        $hasCustom = (int) $chk->get_result()->fetch_assoc()['cnt'] > 0;



        sendJSON([

            'success' => true,

            'classes' => $classes,

            'has_custom' => $hasCustom,

        ]);

    } catch (Exception $e) {

        error_log("getChurchClasses error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في جلب الفصول']);

    }

}



// ===== GET COUPON LOGS =====

function getCouponLogs()

{

    checkAuth();



    try {

        $studentId = intval($_POST['studentId'] ?? 0);



        if ($studentId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف الطفل مطلوب']);

            return;

        }



        $conn = getDBConnection();



        $stmt = $conn->prepare("

            SELECT 

                cl.*,

                u.name as uncle_name,

                DATE_FORMAT(cl.created_at, '%d/%m/%Y %H:%i') as formatted_date

            FROM coupon_logs cl

            LEFT JOIN uncles u ON cl.uncle_id = u.id

            WHERE cl.student_id = ?

            ORDER BY cl.created_at DESC

        ");

        $stmt->bind_param("i", $studentId);

        $stmt->execute();

        $result = $stmt->get_result();



        $logs = [];

        while ($row = $result->fetch_assoc()) {

            $logs[] = [

                'id' => $row['id'],

                'old_count' => $row['old_count'],

                'new_count' => $row['new_count'],

                'change_amount' => $row['change_amount'],

                'change_type' => $row['change_type'],

                'reason' => $row['reason'],

                'uncle_name' => $row['uncle_name'] ?? 'النظام',

                'created_at' => $row['formatted_date']

            ];

        }



        sendJSON(['success' => true, 'logs' => $logs]);



    } catch (Exception $e) {

        error_log("getCouponLogs error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في جلب سجل الكوبونات']);

    }

}

function saveChurchClasses()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $rawData = $_POST['classes'] ?? '[]';

        $classes = json_decode($rawData, true);



        if (!is_array($classes)) {

            sendJSON(['success' => false, 'message' => 'بيانات الفصول غير صالحة']);

            return;

        }



        $conn = getDBConnection();

        $conn->begin_transaction();



        // ── RESET path: empty array means revert to global defaults ──

        // We NEVER delete rows that have students assigned to them.

        // Instead we mark them inactive so class_id FK links stay intact.

        if (count($classes) === 0) {

            $stmt0 = $conn->prepare("UPDATE church_classes SET is_active = 0 WHERE church_id = ?");

            $stmt0->bind_param("i", $churchId);

            $stmt0->execute();

            $conn->commit();

            sendJSON(['success' => true, 'message' => 'تم إعادة الفصول للإعدادات الافتراضية']);

            return;

        }



        // ── Collect the IDs that came back from the client ───────────

        // IDs that are null/missing = new rows to INSERT.

        // IDs that exist           = UPDATE in place (preserves class_id).

        $submittedIds = [];

        foreach ($classes as $cls) {

            if (!empty($cls['id']) && is_numeric($cls['id'])) {

                $submittedIds[] = (int) $cls['id'];

            }

        }



        // ── Soft-deactivate any existing class that was REMOVED ───────

        // We never hard-delete because students still reference the old id.

        if (!empty($submittedIds)) {

            $placeholders = implode(',', array_fill(0, count($submittedIds), '?'));

            $types = 'i' . str_repeat('i', count($submittedIds));

            $deactivateStmt = $conn->prepare("

                UPDATE church_classes

                SET    is_active = 0

                WHERE  church_id = ?

                  AND  id NOT IN ($placeholders)

            ");

            $bindArgs = array_merge([$churchId], $submittedIds);

            $deactivateStmt->bind_param($types, ...$bindArgs);

            $deactivateStmt->execute();

        } else {

            // No existing IDs submitted — deactivate everything

            $stmt0 = $conn->prepare("UPDATE church_classes SET is_active = 0 WHERE church_id = ?");

            $stmt0->bind_param("i", $churchId);

            $stmt0->execute();

        }



        // ── Upsert each submitted class ───────────────────────────────

        // • Existing row (has id): UPDATE name/code/order/color/icon, re-activate.

        // • New row (no id):       INSERT — gets a fresh auto-increment id.

        ensureChurchClassesOrderColumn($conn);



        $updateStmt = $conn->prepare("

            UPDATE church_classes

            SET    arabic_name   = ?,

                   code          = ?,

                   display_order = ?,

                   `order`       = ?,

                   color         = ?,

                   icon          = ?,

                   is_active     = 1

            WHERE  id = ? AND church_id = ?

        ");



        $insertStmt = $conn->prepare("

            INSERT INTO church_classes

                (church_id, code, arabic_name, display_order, `order`, color, icon, is_active)

            VALUES (?, ?, ?, ?, ?, ?, ?, 1)

        ");



        $order = 1;

        foreach ($classes as $cls) {

            $id = !empty($cls['id']) && is_numeric($cls['id']) ? (int) $cls['id'] : null;

            $name = sanitize($cls['arabic_name'] ?? '');

            $code = sanitize($cls['code'] ?? '');

            $color = sanitize($cls['color'] ?? '#4f46e5');

            $icon = sanitize($cls['icon'] ?? '');

            $order = intval($cls['display_order'] ?? $order);



            if (empty($name)) {

                $order++;

                continue;

            }

            if (empty($code))

                $code = 'cls_' . $churchId . '_' . $order . '_' . time();



            if ($id !== null) {

                // UPDATE existing row — id stays the same, students keep their class_id

                $updateStmt->bind_param("ssiissii", $name, $code, $order, $order, $color, $icon, $id, $churchId);

                $updateStmt->execute();

                if ($updateStmt->affected_rows === 0) {

                    // Row didn't exist with that id+church — treat as new

                    $insertStmt->bind_param("issiiss", $churchId, $code, $name, $order, $order, $color, $icon);

                    $insertStmt->execute();

                }

            } else {

                // INSERT brand-new class

                $insertStmt->bind_param("issiiss", $churchId, $code, $name, $order, $order, $color, $icon);

                $insertStmt->execute();

            }

            $order++;

        }



        $conn->commit();

        sendJSON(['success' => true, 'message' => 'تم حفظ الفصول بنجاح']);



    } catch (Exception $e) {

        if (isset($conn))

            $conn->rollback();

        error_log("saveChurchClasses error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في حفظ الفصول: ' . $e->getMessage()]);

    }

}



// ── ADD single custom class ───────────────────────────────────

function addChurchClass()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $code = sanitize($_POST['code'] ?? '');

        $name = sanitize($_POST['arabic_name'] ?? '');

        $order = intval($_POST['display_order'] ?? 99);



        if (empty($code) || empty($name)) {

            sendJSON(['success' => false, 'message' => 'الرمز والاسم مطلوبان']);

            return;

        }



        $conn = getDBConnection();



        // If this church has no custom classes yet, copy the global ones first

        $chk = $conn->prepare("SELECT COUNT(*) AS cnt FROM church_classes WHERE church_id = ?");

        $chk->bind_param("i", $churchId);

        $chk->execute();

        $hasCustom = (int) $chk->get_result()->fetch_assoc()['cnt'] > 0;



        if (!$hasCustom) {

            $globals = $conn->query("SELECT code, arabic_name, display_order FROM classes ORDER BY display_order");

            $copyIns = $conn->prepare("INSERT IGNORE INTO church_classes (church_id, code, arabic_name, display_order) VALUES (?, ?, ?, ?)");

            while ($g = $globals->fetch_assoc()) {

                $copyIns->bind_param("issi", $churchId, $g['code'], $g['arabic_name'], $g['display_order']);

                $copyIns->execute();

            }

        }



        $stmt = $conn->prepare("

            INSERT INTO church_classes (church_id, code, arabic_name, display_order)

            VALUES (?, ?, ?, ?)

        ");

        $stmt->bind_param("issi", $churchId, $code, $name, $order);



        if ($stmt->execute()) {

            sendJSON([

                'success' => true,

                'message' => 'تم إضافة الفصل بنجاح',

                'id' => $conn->insert_id,

            ]);

        } else {

            if ($conn->errno === 1062) {

                sendJSON(['success' => false, 'message' => 'رمز الفصل موجود بالفعل']);

            } else {

                sendJSON(['success' => false, 'message' => 'فشل في إضافة الفصل: ' . $conn->error]);

            }

        }

    } catch (Exception $e) {

        error_log("addChurchClass error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في إضافة الفصل']);

    }

}



// ── UPDATE a single custom class ─────────────────────────────

function updateChurchClass()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $classId = intval($_POST['class_id'] ?? 0);

        $name = sanitize($_POST['arabic_name'] ?? '');

        $order = intval($_POST['display_order'] ?? 0);



        if ($classId === 0 || empty($name)) {

            sendJSON(['success' => false, 'message' => 'بيانات غير كاملة']);

            return;

        }



        $conn = getDBConnection();

        $stmt = $conn->prepare("

            UPDATE church_classes

            SET    arabic_name = ?, display_order = ?, updated_at = NOW()

            WHERE  id = ? AND church_id = ?

        ");

        $stmt->bind_param("siii", $name, $order, $classId, $churchId);



        if ($stmt->execute() && $stmt->affected_rows > 0) {

            // Keep students.class text in sync for this church

            $sync = $conn->prepare("

                UPDATE students s

                JOIN   church_classes cc ON s.class_id = cc.id

                SET    s.class = cc.arabic_name

                WHERE  cc.id = ? AND s.church_id = ?

            ");

            $sync->bind_param("ii", $classId, $churchId);

            $sync->execute();



            sendJSON(['success' => true, 'message' => 'تم تحديث الفصل بنجاح']);

        } else {

            sendJSON(['success' => false, 'message' => 'لم يتم العثور على الفصل أو لا يوجد تغيير']);

        }

    } catch (Exception $e) {

        error_log("updateChurchClass error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحديث الفصل']);

    }

}

function deleteAttendance()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $attendanceId = intval($_POST['attendance_id'] ?? 0);



        if ($attendanceId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف الحضور مطلوب']);

            return;

        }



        $conn = getDBConnection();



        // BEFORE the DELETE, get the old record

        $oldAtt = getAttendanceSnapshot($attendanceId);



        $stmt = $conn->prepare("DELETE FROM attendance WHERE id = ? AND church_id = ?");

        $stmt->bind_param("ii", $attendanceId, $churchId);



        if ($stmt->execute() && $stmt->affected_rows > 0) {

            // ► AUDIT

            auditAttendanceDelete($attendanceId, $oldAtt ?? ['id' => $attendanceId]);



            sendJSON(['success' => true, 'message' => 'تم حذف سجل الحضور بنجاح']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في حذف سجل الحضور']);

        }



    } catch (Exception $e) {

        error_log("deleteAttendance error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في حذف سجل الحضور']);

    }

}

// ── DELETE a single custom class ─────────────────────────────

function deleteChurchClass()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $classId = intval($_POST['class_id'] ?? 0);



        if ($classId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف الفصل مطلوب']);

            return;

        }



        $conn = getDBConnection();



        // Safety: don't delete if students are enrolled

        $chk = $conn->prepare("SELECT COUNT(*) AS cnt FROM students WHERE class_id = ? AND church_id = ?");

        $chk->bind_param("ii", $classId, $churchId);

        $chk->execute();

        $count = (int) $chk->get_result()->fetch_assoc()['cnt'];



        if ($count > 0) {

            sendJSON([

                'success' => false,

                'message' => "لا يمكن حذف الفصل لأنه يحتوي على $count طفل. انقل الأطفال أولاً.",

            ]);

            return;

        }



        $stmt = $conn->prepare("UPDATE church_classes SET is_active = 0 WHERE id = ? AND church_id = ?");

        $stmt->bind_param("ii", $classId, $churchId);



        if ($stmt->execute() && $stmt->affected_rows > 0) {

            sendJSON(['success' => true, 'message' => 'تم حذف الفصل بنجاح']);

        } else {

            sendJSON(['success' => false, 'message' => 'لم يتم العثور على الفصل']);

        }

    } catch (Exception $e) {

        error_log("deleteChurchClass error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في حذف الفصل']);

    }

}



// ── RESET church to global defaults ──────────────────────────

function resetChurchClasses()

{

    try {

        checkAuth();

        $churchId = getChurchId();



        $conn = getDBConnection();



        // Make sure no students use these custom class IDs

        $chk = $conn->prepare("

            SELECT COUNT(*) AS cnt FROM students s

            JOIN church_classes cc ON s.class_id = cc.id

            WHERE cc.church_id = ?

        ");

        $chk->bind_param("i", $churchId);

        $chk->execute();

        $count = (int) $chk->get_result()->fetch_assoc()['cnt'];



        if ($count > 0) {

            sendJSON([

                'success' => false,

                'message' => "يوجد $count طفل مرتبط بفصول مخصصة. يرجى نقلهم للفصول الافتراضية أولاً.",

            ]);

            return;

        }



        // Soft-deactivate instead of hard DELETE — preserves FK links from students

        $del = $conn->prepare("UPDATE church_classes SET is_active = 0 WHERE church_id = ?");

        $del->bind_param("i", $churchId);

        $del->execute();



        sendJSON(['success' => true, 'message' => 'تم إعادة الفصول للإعدادات الافتراضية']);

    } catch (Exception $e) {

        error_log("resetChurchClasses error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في إعادة الضبط']);

    }

}



// ── REORDER classes (drag-and-drop save) ─────────────────────

function reorderChurchClasses()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $ordered = json_decode($_POST['ordered_ids'] ?? '[]', true); // array of class IDs in new order



        if (!is_array($ordered) || count($ordered) === 0) {

            sendJSON(['success' => false, 'message' => 'لا توجد بيانات']);

            return;

        }



        $conn = getDBConnection();

        $upd = $conn->prepare("

            UPDATE church_classes SET display_order = ?

            WHERE id = ? AND church_id = ?

        ");



        foreach ($ordered as $i => $id) {

            $newOrder = $i + 1;

            $id = intval($id);

            $upd->bind_param("iii", $newOrder, $id, $churchId);

            $upd->execute();

        }



        sendJSON(['success' => true, 'message' => 'تم حفظ الترتيب']);

    } catch (Exception $e) {

        error_log("reorderChurchClasses error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في حفظ الترتيب']);

    }

}



// ── ADMIN ONLY: get classes for any church (developer view) ──

function getChurchClassesForAdmin()

{

    try {

        checkAuth();

        $role = $_SESSION['uncle_role'] ?? 'uncle';

        if (!in_array($role, ['admin', 'developer'])) {

            sendJSON(['success' => false, 'message' => 'غير مصرح']);

            return;

        }



        $targetChurchId = intval($_POST['target_church_id'] ?? getChurchId());

        $classes = getClassesForChurch($targetChurchId);



        $conn = getDBConnection();

        $chk = $conn->prepare("SELECT COUNT(*) AS cnt FROM church_classes WHERE church_id = ?");

        $chk->bind_param("i", $targetChurchId);

        $chk->execute();

        $hasCustom = (int) $chk->get_result()->fetch_assoc()['cnt'] > 0;



        sendJSON([

            'success' => true,

            'classes' => $classes,

            'has_custom' => $hasCustom,

            'church_id' => $targetChurchId,

        ]);

    } catch (Exception $e) {

        error_log("getChurchClassesForAdmin error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في جلب الفصول']);

    }

}







function getTrips()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        if ($churchId <= 0) {

            sendJSON(['success' => true, 'trips' => [], 'count' => 0]);

            return;

        }



        $status = sanitize($_POST['status'] ?? $_GET['status'] ?? '');

        $type = sanitize($_POST['type'] ?? $_GET['type'] ?? '');

        $ownOnlyRaw = $_POST['own_only'] ?? $_GET['own_only'] ?? '';

        $ownOnly = in_array(strtolower((string) $ownOnlyRaw), ['1', 'true', 'yes'], true);



        $conn = getDBConnection();



        $sql = "SELECT 

                    t.*,

                    u.name as created_by_name,

                    (t.church_id = ?) as is_owner,

                    (SELECT COUNT(*) FROM trip_registrations WHERE trip_id = t.id AND cancelled = 0) as registered_count,

                    (SELECT COUNT(*) FROM trip_registrations WHERE trip_id = t.id AND cancelled = 0 AND payment_status = 'paid') as paid_count

                FROM trips t

                LEFT JOIN uncles u ON t.created_by = u.id

                WHERE ";



        if ($ownOnly) {

            $sql .= "t.church_id = ?";

            $params = [$churchId, $churchId];

            $types = "ii";

        } else {

            // Scalar JSON_CONTAINS at '$' avoids JSON_ARRAY false positives (e.g. id 1 matching 10)

            // Binds the parameter as an integer to avoid MariaDB syntax errors with CAST(? AS JSON)

            $sql .= "(t.church_id = ? OR JSON_CONTAINS(COALESCE(NULLIF(NULLIF(t.collaborating_churches, ''), '[]'), JSON_ARRAY()), ?))";

            $params = [$churchId, $churchId, (int) $churchId];

            $types = "iii";

        }



        if (!empty($status) && $status !== 'all') {

            $sql .= " AND t.status = ?";

            $params[] = $status;

            $types .= "s";

        }



        if (!empty($type) && $type !== 'all') {

            $sql .= " AND t.type = ?";

            $params[] = $type;

            $types .= "s";

        }



        $sql .= " ORDER BY 

                    CASE t.status

                        WHEN 'planned' THEN 1

                        WHEN 'active' THEN 2

                        WHEN 'completed' THEN 3

                        WHEN 'cancelled' THEN 4

                    END,

                    t.start_date DESC";



        $stmt = $conn->prepare($sql);

        $stmt->bind_param($types, ...$params);

        $stmt->execute();

        $result = $stmt->get_result();



        $trips = [];

        while ($row = $result->fetch_assoc()) {

            if (!$ownOnly && !churchIsTripParticipant($row, $churchId)) {

                continue;

            }



            // Clean up self-collaboration on the fly:

            if (!empty($row['collaborating_churches'])) {

                $collabArr = json_decode($row['collaborating_churches'], true);

                if (is_array($collabArr)) {

                    $ownerChurchId = intval($row['church_id']);

                    $cleanedCollab = array_values(array_filter(array_map('intval', $collabArr), function($id) use ($ownerChurchId) {

                        return $id !== $ownerChurchId;

                    }));

                    if ($cleanedCollab !== $collabArr) {

                        $newCollabJson = json_encode($cleanedCollab, JSON_UNESCAPED_UNICODE);

                        $u = $conn->prepare("UPDATE trips SET collaborating_churches = ? WHERE id = ?");

                        $u->bind_param('si', $newCollabJson, $row['id']);

                        $u->execute();

                        $row['collaborating_churches'] = $newCollabJson;

                    }

                }

            }



            // حساب السعر بعد الخصم

            $finalPrice = $row['price'];

            if ($row['discount'] > 0) {

                if ($row['discount_type'] === 'percentage') {

                    $finalPrice = $row['price'] - ($row['price'] * $row['discount'] / 100);

                } else {

                    $finalPrice = max(0, $row['price'] - $row['discount']);

                }

            }



            $row['final_price'] = round($finalPrice, 2);

            $row['start_date_formatted'] = $row['start_date'] ? date('d/m/Y', strtotime($row['start_date'])) : '';

            $row['end_date_formatted'] = $row['end_date'] ? date('d/m/Y', strtotime($row['end_date'])) : '';

            $row['collaborators_list'] = getCollaboratingChurchesList($conn, $row['collaborating_churches'] ?? '', intval($row['church_id']));

            $row['is_owner'] = intval($row['is_owner'] ?? 0);

            if (!empty($row['custom_field_icons'])) {

                $dec = json_decode($row['custom_field_icons'], true);

                $row['custom_field_icons'] = is_array($dec) ? $dec : [];

            } else {

                $row['custom_field_icons'] = [];

            }

            // If custom_fields is empty but icons are present, derive fields from the icon keys and persist

            if ((empty($row['custom_fields']) || in_array(strtolower(trim($row['custom_fields'])), ['0', 'null', 'undefined'], true)) && !empty($row['custom_field_icons'])) {

                $keys = array_keys($row['custom_field_icons']);

                $keys = array_filter(array_map('trim', $keys), function ($f) {

                    return $f !== '';

                });

                if (count($keys)) {

                    $derived = implode(',', $keys);

                    $row['custom_fields'] = $derived;

                    try {

                        $u = $conn->prepare("UPDATE trips SET custom_fields = ? WHERE id = ?");

                        $u->bind_param('si', $derived, $row['id']);

                        $u->execute();

                    } catch (Exception $e) {

                        // don't break listing on failure to update

                    }

                }

            }



            $trips[] = $row;

        }



        sendJSON([

            'success' => true,

            'trips' => $trips,

            'count' => count($trips)

        ]);



    } catch (Exception $e) {

        error_log("getTrips error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في جلب الرحلات / المؤتمرات: ' . $e->getMessage()]);

    }

}



function addTrip()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $uncleId = $_SESSION['uncle_id'] ?? null;



        $title = sanitize($_POST['title'] ?? '');

        $description = sanitize($_POST['description'] ?? '');

        $type = sanitize($_POST['type'] ?? 'one_day');

        $startDate = sanitize($_POST['start_date'] ?? '');

        $endDate = sanitize($_POST['end_date'] ?? '');

        $price = floatval($_POST['price'] ?? 0);

        $discount = floatval($_POST['discount'] ?? 0);

        $discountType = sanitize($_POST['discount_type'] ?? 'percentage');

        $maxParticipants = intval($_POST['max_participants'] ?? 0);

        $status = sanitize($_POST['status'] ?? 'planned');

        $showRegisteredKids = isset($_POST['show_registered_kids']) ? intval($_POST['show_registered_kids']) : 1;

        $showRegisteredKids = $showRegisteredKids ? 1 : 0;

        $hasPointsGame = isset($_POST['has_points_game']) ? intval($_POST['has_points_game']) : 0;

        $hasPointsGame = $hasPointsGame ? 1 : 0;

        $customFields = strip_tags(trim($_POST['custom_fields'] ?? ''));

        if (in_array(strtolower($customFields), ['0', 'null', 'undefined'], true)) {

            $customFields = '';

        }

        $customFields = implode(',', array_filter(array_map('trim', explode(',', $customFields)), function ($field) {

            return $field !== '';

        }));

        // custom_field_icons — includes nested sub_fields; sanitize scalars but preserve structure

        $customFieldIconsRaw = $_POST['custom_field_icons'] ?? '';

        $customFieldIcons = null;

        if (!empty($customFieldIconsRaw)) {

            $decoded = json_decode($customFieldIconsRaw, true);

            if (is_array($decoded)) {

                $sanitizedIcons = [];

                foreach ($decoded as $fieldName => $fieldMeta) {

                    $cleanName = strip_tags(trim((string) $fieldName));

                    if ($cleanName === '')

                        continue;

                    $sanitizedMeta = [

                        'icon' => strip_tags(trim($fieldMeta['icon'] ?? 'fas fa-tag')),

                        'type' => strip_tags(trim($fieldMeta['type'] ?? 'choices')),

                        'choices' => array_values(array_filter(

                            array_map(

                                function ($c) {

                                    return strip_tags(trim((string) $c));

                                },

                                is_array($fieldMeta['choices'] ?? null) ? $fieldMeta['choices'] : []

                            ),

                            function ($c) {

                                return $c !== '';

                            }

                        )),

                    ];

                    // Preserve sub_fields: can be choice-based map to arrays of objects [{name, icon, choices}] OR legacy {choice: {name: {icon, choices}}}

                    if (!empty($fieldMeta['sub_fields']) && is_array($fieldMeta['sub_fields'])) {

                        $cleanSubFields = [];

                        foreach ($fieldMeta['sub_fields'] as $key => $subData) {

                            $cleanKey = strip_tags(trim((string) $key));

                            if ($cleanKey === '' || !is_array($subData))

                                continue;



                            // If $subData is an array of objects (new format used by frontend)

                            if (isset($subData[0]) && is_array($subData[0])) {

                                $cleanSubFields[$cleanKey] = [];

                                foreach ($subData as $sf) {

                                    if (!is_array($sf))

                                        continue;

                                    $subName = strip_tags(trim((string) ($sf['name'] ?? '')));

                                    if ($subName === '')

                                        continue;

                                    $cleanSubFields[$cleanKey][] = [

                                        'name' => $subName,

                                        'icon' => strip_tags(trim((string) ($sf['icon'] ?? 'fas fa-tag'))),

                                        'type' => strip_tags(trim((string) ($sf['type'] ?? 'choices'))),

                                        'choices' => array_values(array_filter(

                                            array_map(function ($c) {

                                                return strip_tags(trim((string) $c));

                                            }, is_array($sf['choices'] ?? null) ? $sf['choices'] : []),

                                            function ($c) {

                                                return $c !== '';

                                            }

                                        ))

                                    ];

                                }

                            } else {

                                // Legacy format or direct name-indexed object

                                $cleanSubFields[$cleanKey] = [];

                                foreach ($subData as $subName => $subMeta) {

                                    $cleanSubName = strip_tags(trim((string) $subName));

                                    if ($cleanSubName === '')

                                        continue;

                                    $cleanSubFields[$cleanKey][$cleanSubName] = [

                                        'icon' => strip_tags(trim($subMeta['icon'] ?? 'fas fa-tag')),

                                        'type' => strip_tags(trim($subMeta['type'] ?? 'choices')),

                                        'choices' => array_values(array_filter(

                                            array_map(function ($c) {

                                                return strip_tags(trim((string) $c));

                                            }, is_array($subMeta['choices'] ?? null) ? $subMeta['choices'] : []),

                                            function ($c) {

                                                return $c !== '';

                                            }

                                        )),

                                    ];

                                }

                            }

                        }

                        if (!empty($cleanSubFields)) {

                            $sanitizedMeta['sub_fields'] = $cleanSubFields;

                        }

                    }

                    $sanitizedIcons[$cleanName] = $sanitizedMeta;

                }

                $customFieldIcons = json_encode($sanitizedIcons, JSON_UNESCAPED_UNICODE);

            }

        }

        // If custom fields are empty but icons were sent as keys, derive fields from icons keys

        if ((empty($customFields) || $customFields === '0') && !empty($customFieldIconsRaw)) {

            $tryDec = json_decode($customFieldIconsRaw, true);

            if (is_array($tryDec) && count($tryDec) > 0) {

                $keys = array_keys($tryDec);

                $keys = array_filter(array_map('trim', $keys), function ($f) {

                    return $f !== '';

                });

                if (count($keys)) {

                    $customFields = implode(',', $keys);

                }

            }

        }



        if (empty($title) || empty($startDate)) {

            sendJSON(['success' => false, 'message' => 'العنوان وتاريخ البدء مطلوبان']);

            return;

        }



        // تحويل التاريخ

        $dbStartDate = formatDateToDB($startDate);

        $dbEndDate = !empty($endDate) ? formatDateToDB($endDate) : null;



        if (!$dbStartDate) {

            sendJSON(['success' => false, 'message' => 'تاريخ البدء غير صحيح']);

            return;

        }



        $conn = getDBConnection();



        // معالجة رفع الصورة

        $imageUrl = '';

        if (isset($_FILES['trip_image']) && $_FILES['trip_image']['error'] === UPLOAD_ERR_OK) {

            $uploadDir = __DIR__ . '/uploads/trips/';



            if (!is_dir($uploadDir)) {

                mkdir($uploadDir, 0755, true);

            }



            $filename = 'trip_' . time() . '_' . uniqid() . '.jpg';

            $uploadPath = $uploadDir . $filename;



            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];

            $fileType = mime_content_type($_FILES['trip_image']['tmp_name']);



            if (in_array($fileType, $allowedTypes)) {

                if (move_uploaded_file($_FILES['trip_image']['tmp_name'], $uploadPath)) {

                    $imageUrl = "https://sunday-school.online/uploads/trips/" . $filename;

                }

            }

        }



        $stmt = $conn->prepare("

            INSERT INTO trips (

                church_id, title, description, type, 

                start_date, end_date, price, discount, 

                discount_type, max_participants, status, 

                image_url, created_by, show_registered_kids,

                has_points_game, custom_fields, custom_field_icons

            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)

        ");



        $stmt->bind_param(

            "isssssddsissiiiss",

            $churchId,

            $title,

            $description,

            $type,

            $dbStartDate,

            $dbEndDate,

            $price,

            $discount,

            $discountType,

            $maxParticipants,

            $status,

            $imageUrl,

            $uncleId,

            $showRegisteredKids,

            $hasPointsGame,

            $customFields,

            $customFieldIcons

        );



        if ($stmt->execute()) {

            $tripId = $conn->insert_id;



            // تسجيل النشاط

            logActivity($churchId, $uncleId, 'add_trip', "إضافة رحلة: $title");



            // ► AUDIT

            auditTripAdd($tripId, $title, [

                'title' => $title,

                'type' => $type,

                'start_date' => $dbStartDate,

                'end_date' => $dbEndDate,

                'price' => $price,

                'discount' => $discount,

                'max_participants' => $maxParticipants,

                'status' => $status,

                'show_registered_kids' => $showRegisteredKids,

            ]);



            sendJSON([

                'success' => true,

                'message' => 'تم إضافة الرحلة بنجاح',

                'trip_id' => $tripId,

                'image_url' => $imageUrl

            ]);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في إضافة الرحلة: ' . $stmt->error]);

        }



    } catch (Exception $e) {

        error_log("addTrip error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في إضافة الرحلة: ' . $e->getMessage()]);

    }

}

function updateTrip()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $tripId = intval($_POST['trip_id'] ?? 0);



        if ($tripId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف الرحلة مطلوب']);

            return;

        }



        $title = sanitize($_POST['title'] ?? '');

        $description = sanitize($_POST['description'] ?? '');

        $type = sanitize($_POST['type'] ?? 'one_day');

        $startDate = sanitize($_POST['start_date'] ?? '');

        $endDate = sanitize($_POST['end_date'] ?? '');

        $price = floatval($_POST['price'] ?? 0);

        $discount = floatval($_POST['discount'] ?? 0);

        $discountType = sanitize($_POST['discount_type'] ?? 'percentage');

        $maxParticipants = intval($_POST['max_participants'] ?? 0);

        $status = sanitize($_POST['status'] ?? 'planned');

        $showRegisteredKids = isset($_POST['show_registered_kids']) ? intval($_POST['show_registered_kids']) : 1;

        $showRegisteredKids = $showRegisteredKids ? 1 : 0;

        $hasPointsGame = isset($_POST['has_points_game']) ? intval($_POST['has_points_game']) : 0;

        $hasPointsGame = $hasPointsGame ? 1 : 0;

        $customFields = strip_tags(trim($_POST['custom_fields'] ?? ''));

        if (in_array(strtolower($customFields), ['0', 'null', 'undefined'], true)) {

            $customFields = '';

        }

        $customFields = implode(',', array_filter(array_map('trim', explode(',', $customFields)), function ($field) {

            return $field !== '';

        }));

        // custom_field_icons — includes nested sub_fields; sanitize scalars but preserve structure

        $customFieldIconsRaw = $_POST['custom_field_icons'] ?? '';

        $customFieldIcons = null;

        if (!empty($customFieldIconsRaw)) {

            $decoded = json_decode($customFieldIconsRaw, true);

            if (is_array($decoded)) {

                $sanitizedIcons = [];

                foreach ($decoded as $fieldName => $fieldMeta) {

                    $cleanName = strip_tags(trim((string) $fieldName));

                    if ($cleanName === '')

                        continue;

                    $sanitizedMeta = [

                        'icon' => strip_tags(trim($fieldMeta['icon'] ?? 'fas fa-tag')),

                        'type' => strip_tags(trim($fieldMeta['type'] ?? 'choices')),

                        'choices' => array_values(array_filter(

                            array_map(

                                function ($c) {

                                    return strip_tags(trim((string) $c));

                                },

                                is_array($fieldMeta['choices'] ?? null) ? $fieldMeta['choices'] : []

                            ),

                            function ($c) {

                                return $c !== '';

                            }

                        )),

                    ];

                    // Preserve sub_fields: can be choice-based map to arrays of objects [{name, icon, choices}] OR legacy {choice: {name: {icon, choices}}}

                    if (!empty($fieldMeta['sub_fields']) && is_array($fieldMeta['sub_fields'])) {

                        $cleanSubFields = [];

                        foreach ($fieldMeta['sub_fields'] as $key => $subData) {

                            $cleanKey = strip_tags(trim((string) $key));

                            if ($cleanKey === '' || !is_array($subData))

                                continue;



                            // If $subData is an array of objects (new format used by frontend)

                            if (isset($subData[0]) && is_array($subData[0])) {

                                $cleanSubFields[$cleanKey] = [];

                                foreach ($subData as $sf) {

                                    if (!is_array($sf))

                                        continue;

                                    $subName = strip_tags(trim((string) ($sf['name'] ?? '')));

                                    if ($subName === '')

                                        continue;

                                    $cleanSubFields[$cleanKey][] = [

                                        'name' => $subName,

                                        'icon' => strip_tags(trim((string) ($sf['icon'] ?? 'fas fa-tag'))),

                                        'type' => strip_tags(trim((string) ($sf['type'] ?? 'choices'))),

                                        'choices' => array_values(array_filter(

                                            array_map(function ($c) {

                                                return strip_tags(trim((string) $c));

                                            }, is_array($sf['choices'] ?? null) ? $sf['choices'] : []),

                                            function ($c) {

                                                return $c !== '';

                                            }

                                        ))

                                    ];

                                }

                            } else {

                                // Legacy format or direct name-indexed object

                                $cleanSubFields[$cleanKey] = [];

                                foreach ($subData as $subName => $subMeta) {

                                    $cleanSubName = strip_tags(trim((string) $subName));

                                    if ($cleanSubName === '')

                                        continue;

                                    $cleanSubFields[$cleanKey][$cleanSubName] = [

                                        'icon' => strip_tags(trim($subMeta['icon'] ?? 'fas fa-tag')),

                                        'type' => strip_tags(trim($subMeta['type'] ?? 'choices')),

                                        'choices' => array_values(array_filter(

                                            array_map(function ($c) {

                                                return strip_tags(trim((string) $c));

                                            }, is_array($subMeta['choices'] ?? null) ? $subMeta['choices'] : []),

                                            function ($c) {

                                                return $c !== '';

                                            }

                                        )),

                                    ];

                                }

                            }

                        }

                        if (!empty($cleanSubFields)) {

                            $sanitizedMeta['sub_fields'] = $cleanSubFields;

                        }

                    }

                    $sanitizedIcons[$cleanName] = $sanitizedMeta;

                }

                $customFieldIcons = json_encode($sanitizedIcons, JSON_UNESCAPED_UNICODE);

            }

        }

        // If custom fields are empty but icons were sent, derive fields from the icon keys

        if ((empty($customFields) || $customFields === '0') && !empty($customFieldIconsRaw)) {

            $tryDec = json_decode($customFieldIconsRaw, true);

            if (is_array($tryDec) && count($tryDec) > 0) {

                $keys = array_keys($tryDec);

                $keys = array_filter(array_map('trim', $keys), function ($f) {

                    return $f !== '';

                });

                if (count($keys)) {

                    $customFields = implode(',', $keys);

                }

            }

        }



        if (empty($title) || empty($startDate)) {

            sendJSON(['success' => false, 'message' => 'العنوان وتاريخ البدء مطلوبان']);

            return;

        }



        $dbStartDate = formatDateToDB($startDate);

        $dbEndDate = !empty($endDate) ? formatDateToDB($endDate) : null;



        $conn = getDBConnection();



        // BEFORE update, get old trip data

        $oldTrip = getTripSnapshot($tripId);



        $stmt = $conn->prepare("

            UPDATE trips 

            SET title = ?, description = ?, type = ?, 

                start_date = ?, end_date = ?, price = ?, 

                discount = ?, discount_type = ?, max_participants = ?, 

                status = ?, show_registered_kids = ?, has_points_game = ?, custom_fields = ?, custom_field_icons = ?, updated_at = NOW()

            WHERE id = ? AND church_id = ?

        ");



        $stmt->bind_param(

            "sssssddsisiiissi",

            $title,

            $description,

            $type,

            $dbStartDate,

            $dbEndDate,

            $price,

            $discount,

            $discountType,

            $maxParticipants,

            $status,

            $showRegisteredKids,

            $hasPointsGame,

            $customFields,

            $customFieldIcons,

            $tripId,

            $churchId

        );



        if ($stmt->execute()) {

            // AFTER update, get new trip data

            $newTrip = getTripSnapshot($tripId);



            // ► AUDIT

            auditTripEdit($tripId, $oldTrip ?? [], $newTrip ?? []);



            sendJSON(['success' => true, 'message' => 'تم تحديث الرحلة بنجاح']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في تحديث الرحلة: ' . $stmt->error]);

        }



    } catch (Exception $e) {

        error_log("updateTrip error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحديث الرحلة: ' . $e->getMessage()]);

    }

}

function deleteTrip()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $tripId = intval($_POST['trip_id'] ?? 0);



        if ($tripId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف الرحلة مطلوب']);

            return;

        }



        $conn = getDBConnection();



        // BEFORE delete, get old trip data

        $oldTrip = getTripSnapshot($tripId);



        // التحقق من وجود تسجيلات

        $checkStmt = $conn->prepare("

            SELECT COUNT(*) as cnt FROM trip_registrations 

            WHERE trip_id = ? AND cancelled = 0

        ");

        $checkStmt->bind_param("i", $tripId);

        $checkStmt->execute();

        $result = $checkStmt->get_result();

        $count = $result->fetch_assoc()['cnt'];



        if ($count > 0) {

            sendJSON([

                'success' => false,

                'message' => "لا يمكن حذف الرحلة لأن هناك $count طفل مسجل. يمكن إلغاء الرحلة بدلاً من الحذف."

            ]);

            return;

        }



        $stmt = $conn->prepare("DELETE FROM trips WHERE id = ? AND church_id = ?");

        $stmt->bind_param("ii", $tripId, $churchId);



        if ($stmt->execute()) {

            // ► AUDIT

            auditTripDelete($tripId, $oldTrip ?? ['id' => $tripId]);



            sendJSON(['success' => true, 'message' => 'تم حذف الرحلة بنجاح']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في حذف الرحلة']);

        }



    } catch (Exception $e) {

        error_log("deleteTrip error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في حذف الرحلة: ' . $e->getMessage()]);

    }

}

function getTripDetails()

{

    try {

        // Allow both church admins AND uncles to access trip details

        checkUncleAuth();

        $churchId = getChurchId();

        $tripId = intval($_POST['trip_id'] ?? $_GET['trip_id'] ?? 0);



        if ($tripId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف الرحلة مطلوب']);

            return;

        }



        $conn = getDBConnection();

        ensureGuestsTable($conn);



        // معلومات الرحلة — no church_id filter so collaborators can also view

        $tripStmt = $conn->prepare("

            SELECT t.*, u.name as created_by_name

            FROM trips t

            LEFT JOIN uncles u ON t.created_by = u.id

            WHERE t.id = ?

        ");

        $tripStmt->bind_param("i", $tripId);

        $tripStmt->execute();

        $tripResult = $tripStmt->get_result();



        if ($tripResult->num_rows === 0) {

            sendJSON(['success' => false, 'message' => 'الرحلة غير موجودة']);

            return;

        }



        $trip = $tripResult->fetch_assoc();



        // Clean up self-collaboration on the fly:

        if (!empty($trip['collaborating_churches'])) {

            $collabArr = json_decode($trip['collaborating_churches'], true);

            if (is_array($collabArr)) {

                $ownerChurchId = intval($trip['church_id']);

                $cleanedCollab = array_values(array_filter(array_map('intval', $collabArr), function($id) use ($ownerChurchId) {

                    return $id !== $ownerChurchId;

                }));

                if ($cleanedCollab !== $collabArr) {

                    $newCollabJson = json_encode($cleanedCollab, JSON_UNESCAPED_UNICODE);

                    $u = $conn->prepare("UPDATE trips SET collaborating_churches = ? WHERE id = ?");

                    $u->bind_param('si', $newCollabJson, $trip['id']);

                    $u->execute();

                    $trip['collaborating_churches'] = $newCollabJson;

                }

            }

        }



        if (!isTripDeveloperViewer()) {

            if ($churchId <= 0) {

                sendJSON(['success' => false, 'message' => 'يجب تسجيل الدخول ككنيسة لعرض هذه الرحلة']);

                return;

            }

            if (!churchIsTripParticipant($trip, $churchId)) {

                buildTripAccessDeniedResponse($conn, $trip, $churchId);

                return;

            }

        }



        // حساب السعر بعد الخصم

        $finalPrice = $trip['price'];

        if ($trip['discount'] > 0) {

            if ($trip['discount_type'] === 'percentage') {

                $finalPrice = $trip['price'] - ($trip['price'] * $trip['discount'] / 100);

            } else {

                $finalPrice = max(0, $trip['price'] - $trip['discount']);

            }

        }

        $trip['final_price'] = round($finalPrice, 2);

        $totalPerKid = getTripTotalPerKid($conn, $tripId, $trip);

        $trip['total_per_kid'] = $totalPerKid;

        $trip['expenses_per_kid'] = round(getTripExpensesPerKid($conn, $tripId), 2);

        $trip['start_date_formatted'] = date('d/m/Y', strtotime($trip['start_date']));

        $trip['end_date_formatted'] = $trip['end_date'] ? date('d/m/Y', strtotime($trip['end_date'])) : '';

        // decode custom_field_icons JSON into array for JS

        if (!empty($trip['custom_field_icons'])) {

            $decoded = json_decode($trip['custom_field_icons'], true);

            $trip['custom_field_icons'] = is_array($decoded) ? $decoded : [];

        } else {

            $trip['custom_field_icons'] = [];

        }

        // If custom_fields is empty but icons exist, derive and persist custom_fields

        if ((empty($trip['custom_fields']) || in_array(strtolower(trim($trip['custom_fields'])), ['0', 'null', 'undefined'], true)) && !empty($trip['custom_field_icons'])) {

            $keys = array_keys($trip['custom_field_icons']);

            $keys = array_filter(array_map('trim', $keys), function ($f) {

                return $f !== '';

            });

            if (count($keys)) {

                $derived = implode(',', $keys);

                $trip['custom_fields'] = $derived;

                try {

                    $up = $conn->prepare("UPDATE trips SET custom_fields = ? WHERE id = ? AND church_id = ?");

                    $up->bind_param('sii', $derived, $tripId, $churchId);

                    $up->execute();

                } catch (Exception $e) {

                    // ignore update failure

                }

            }

        }



        // قائمة المسجلين

        $regStmt = $conn->prepare("

            SELECT 

                tr.*,

                COALESCE(s.name, g.name) as student_name,

                COALESCE(cc.arabic_name, gc.arabic_name, s.class, g.class) as student_class,

                COALESCE(s.phone, g.phone) as student_phone,

                s.image_url as student_image,

                s.trip_points as student_trip_points,

                COALESCE(s.gender, g.gender) as student_gender,

                g.guardian_name as guest_guardian_name,

                u.name as registered_by_name,

                COALESCE((SELECT SUM(amount) FROM trip_payments WHERE registration_id = tr.id AND is_deleted = 0), 0) as total_paid,

                COALESCE((SELECT SUM(donation) FROM trip_payments WHERE registration_id = tr.id AND is_deleted = 0), 0) as total_donation,

                tr.payment_history

            FROM trip_registrations tr

            LEFT JOIN students s ON tr.student_id = s.id

            LEFT JOIN guests g ON tr.guest_id = g.id

            LEFT JOIN church_classes cc ON cc.id = s.class_id AND cc.church_id = s.church_id AND cc.is_active = 1

            LEFT JOIN classes gc ON gc.id = s.class_id

            LEFT JOIN uncles u ON tr.registered_by = u.id

            WHERE tr.trip_id = ? AND tr.cancelled = 0

            ORDER BY tr.registration_date

        ");

        $regStmt->bind_param("i", $tripId);

        $regStmt->execute();

        $regResult = $regStmt->get_result();



        $registrations = [];

        $totalPaid = 0;

        $totalDonations = 0;

        $pendingAmount = 0;



        // Fetch all payments for this trip to build history with real IDs

        $paymentsStmt = $conn->prepare("

            SELECT tp.*, u.name as received_by_name

            FROM trip_payments tp

            LEFT JOIN uncles u ON tp.received_by = u.id

            JOIN trip_registrations tr ON tp.registration_id = tr.id

            WHERE tr.trip_id = ?

            ORDER BY tp.id ASC

        ");

        $paymentsStmt->bind_param("i", $tripId);

        $paymentsStmt->execute();

        $paymentsResult = $paymentsStmt->get_result();

        $allPayments = [];

        while ($p = $paymentsResult->fetch_assoc()) {

            $allPayments[$p['registration_id']][] = $p;

        }



        while ($row = $regResult->fetch_assoc()) {

            $row['total_paid'] = floatval($row['total_paid'] ?? 0);

            $row['total_donation'] = floatval($row['total_donation'] ?? 0);



            $tp = json_decode($row['student_trip_points'] ?? '{}', true);

            $row['is_naughty'] = $tp["n_{$tripId}"] ?? false;

            $row['trip_points_val'] = intval($tp[$tripId] ?? 0);



            // Build history from real trip_payments table

            $regPayments = $allPayments[$row['id']] ?? [];

            $history = [];

            foreach ($regPayments as $p) {

                $type = 'payment';

                if ($p['amount'] > 0 && (strpos($p['notes'], 'دفعة مقدمة') !== false)) {

                    $type = 'deposit';

                } elseif ($p['amount'] == 0 && $p['donation'] > 0) {

                    $type = 'donation';

                }



                $history[] = [

                    'id' => $p['id'],

                    'type' => $type,

                    'timestamp' => $p['payment_date'] ?? $p['created_at'] ?? null,

                    'amount' => floatval($p['amount']),

                    'donation' => floatval($p['donation']),

                    'payment_method' => $p['payment_method'],

                    'received_by' => $p['received_by_name'] ?: '—',

                    'notes' => $p['notes'],

                    'is_deleted' => intval($p['is_deleted'] ?? 0)

                ];

            }

            $row['payment_history'] = $history;



            $payStatus = tripPaymentStatusFromPaid($row['total_paid'], $totalPerKid);

            $row['remaining'] = $payStatus['remaining'];

            $row['payment_status'] = $payStatus['payment_status'];



            $totalPaid += $row['total_paid'];

            $totalDonations += $row['total_donation'];

            if ($row['remaining'] > 0) {

                $pendingAmount += $row['remaining'];

            }



            $registrations[] = $row;

        }



        ensureWaitlistTable($conn);

        $waitlist = getWaitlistData($tripId, $conn);

        $waitlistCollected = 0.0;

        $waitlistDonations = 0.0;

        $waitlistPending = 0.0;

        foreach ($waitlist as $w) {

            $waitlistCollected += floatval($w['total_paid'] ?? 0);

            $waitlistDonations += floatval($w['total_donation'] ?? 0);

            $waitlistPending += floatval($w['remaining'] ?? 0);

        }



        $trip['registrations'] = $registrations;

        $trip['waitlist'] = $waitlist;

        $participantCount = count($registrations) + count($waitlist);

        $trip['stats'] = [

            'registered' => count($registrations),

            'waitlist_count' => count($waitlist),

            'participant_count' => $participantCount,

            'paid_count' => count(array_filter($registrations, function ($r) {

                return $r['payment_status'] === 'paid';

            })),

            'partial_count' => count(array_filter($registrations, function ($r) {

                return $r['payment_status'] === 'partial';

            })),

            'pending_count' => count(array_filter($registrations, function ($r) {

                return $r['payment_status'] === 'pending';

            })),

            'total_collected' => round($totalPaid + $waitlistCollected, 2),

            'registration_collected' => round($totalPaid, 2),

            'waitlist_collected' => round($waitlistCollected, 2),

            'total_donations' => round($totalDonations + $waitlistDonations, 2),

            'registration_pending' => round($pendingAmount, 2),

            'waitlist_pending' => round($waitlistPending, 2),

            'pending_amount' => round($pendingAmount + $waitlistPending, 2),

            'total_expected' => round($totalPerKid * $participantCount, 2),

            'registration_expected' => round($totalPerKid * count($registrations), 2),

            'waitlist_expected' => round($totalPerKid * count($waitlist), 2),

            'total_per_kid' => $totalPerKid,

        ];



        $trip['collaborators_list'] = getCollaboratingChurchesList($conn, $trip['collaborating_churches'] ?? '', intval($trip['church_id']));

        $trip['is_owner'] = ($churchId > 0 && intval($trip['church_id']) === $churchId) ? 1 : 0;



        sendJSON([

            'success' => true,

            'trip' => $trip,

            'user_role' => $_SESSION['uncle_role'] ?? 'uncle'

        ]);



    } catch (Exception $e) {

        error_log("getTripDetails error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في جلب تفاصيل الرحلة: ' . $e->getMessage()]);

    }

}



function bulkUpdateCustomData()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $tripId = intval($_POST['trip_id'] ?? 0);

        $rawData = $_POST['data'] ?? '';



        if ($tripId === 0 || empty($rawData)) {

            sendJSON(['success' => false, 'message' => 'معرف الرحلة والبيانات مطلوبان']);

            return;

        }



        $updates = json_decode($rawData, true);

        if (!is_array($updates)) {

            sendJSON(['success' => false, 'message' => 'تنسيق البيانات غير صالح']);

            return;

        }



        $conn = getDBConnection();

        if (!verifyTripParticipant($conn, $tripId, $churchId)) {

            sendJSON(['success' => false, 'message' => 'الرحلة غير موجودة أو غير مصرح بها']);

            return;

        }



        $updateStmt = $conn->prepare("UPDATE trip_registrations SET custom_data = ? WHERE id = ? AND trip_id = ? AND cancelled = 0");

        if (!$updateStmt) {

            sendJSON(['success' => false, 'message' => 'فشل إعداد الاستعلام']);

            return;

        }



        $updatedCount = 0;

        foreach ($updates as $row) {

            $registrationId = intval($row['registration_id'] ?? 0);

            $customData = $row['custom_data'] ?? [];

            if ($registrationId <= 0 || !is_array($customData)) {

                continue;

            }



            $normalized = [];

            foreach ($customData as $key => $value) {

                // Preserve key structure — Arabic field names + __sub__ separators

                $fieldKey = strip_tags(trim((string) $key));

                if ($fieldKey === '') {

                    continue;

                }

                $cleanVal = strip_tags(trim((string) $value));

                // Only store if a value was selected

                if ($cleanVal !== '') {

                    $normalized[$fieldKey] = $cleanVal;

                }

            }



            $json = !empty($normalized) ? json_encode($normalized, JSON_UNESCAPED_UNICODE) : null;

            $updateStmt->bind_param('sis', $json, $registrationId, $tripId);

            if ($updateStmt->execute()) {

                if ($updateStmt->affected_rows > 0) {

                    $updatedCount++;

                }

            }

        }



        sendJSON(['success' => true, 'updated' => $updatedCount]);

    } catch (Exception $e) {

        error_log("bulkUpdateCustomData error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحديث البيانات: ' . $e->getMessage()]);

    }

}



// ── WAITLIST HELPERS ──────────────────────────────────────────────────────────

function ensureWaitlistTable($conn)

{

    $conn->query("

        CREATE TABLE IF NOT EXISTS `trip_waitlist` (

            `id`          INT AUTO_INCREMENT PRIMARY KEY,

            `trip_id`     INT NOT NULL,

            `student_id`  INT NOT NULL,

            `church_id`   INT NOT NULL,

            `added_by`    INT DEFAULT NULL,

            `position`    INT NOT NULL DEFAULT 1,

            `notes`       TEXT DEFAULT NULL,

            `deposit`     DECIMAL(10,2) DEFAULT 0,

            `donation`    DECIMAL(10,2) DEFAULT 0,

            `custom_data` TEXT DEFAULT NULL,

            `payment_history` TEXT DEFAULT NULL,

            `added_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            UNIQUE KEY `uq_trip_student` (`trip_id`, `student_id`),

            KEY `idx_trip_pos` (`trip_id`, `position`),

            KEY `idx_church` (`church_id`)

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci

    ");



    // Add payment_history column if it does not exist

    $check = $conn->query("SHOW COLUMNS FROM `trip_waitlist` LIKE 'payment_history'");

    if ($check && $check->num_rows == 0) {

        $conn->query("ALTER TABLE `trip_waitlist` ADD COLUMN `payment_history` TEXT DEFAULT NULL");

    }

}



function getTripDiscountedBasePrice($trip)

{

    $finalPrice = floatval($trip['price'] ?? 0);

    if (floatval($trip['discount'] ?? 0) > 0) {

        if (($trip['discount_type'] ?? '') === 'percentage') {

            $finalPrice = $finalPrice - ($finalPrice * floatval($trip['discount']) / 100);

        } else {

            $finalPrice = max(0, $finalPrice - floatval($trip['discount']));

        }

    }

    return round($finalPrice, 2);

}



function getTripExpensesPerKid($conn, $tripId)

{

    $tableCheck = @$conn->query("SHOW TABLES LIKE 'trip_expenses'");

    if (!$tableCheck || $tableCheck->num_rows === 0) {

        return 0.0;

    }

    $stmt = $conn->prepare("SELECT COALESCE(SUM(per_kid), 0) AS exp_total FROM trip_expenses WHERE trip_id = ?");

    if (!$stmt) {

        return 0.0;

    }

    $stmt->bind_param("i", $tripId);

    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    return floatval($row['exp_total'] ?? 0);

}



function getTripTotalPerKid($conn, $tripId, $trip = null)

{

    if ($trip === null) {

        $stmt = $conn->prepare("SELECT price, discount, discount_type FROM trips WHERE id = ? LIMIT 1");

        $stmt->bind_param("i", $tripId);

        $stmt->execute();

        $trip = $stmt->get_result()->fetch_assoc() ?: [];

    }

    return round(getTripDiscountedBasePrice($trip) + getTripExpensesPerKid($conn, $tripId), 2);

}



function parseWaitlistPaymentHistory($raw)

{

    if (is_array($raw)) {

        return $raw;

    }

    if ($raw === null || $raw === '') {

        return [];

    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];

}



function computeWaitlistPaymentTotals(array $historyArray)

{

    $totalPaid = 0.0;

    $totalDonation = 0.0;

    foreach ($historyArray as $p) {

        if (intval($p['is_deleted'] ?? 0) === 1) {

            continue;

        }

        if (($p['type'] ?? '') === 'donation') {

            $totalDonation += floatval($p['donation'] ?? 0);

        } else {

            $totalPaid += floatval($p['amount'] ?? 0);

        }

    }

    return [

        'total_paid' => round($totalPaid, 2),

        'total_donation' => round($totalDonation, 2),

    ];

}



function tripPaymentStatusFromPaid($totalPaid, $totalPerKid)

{

    $remaining = round(max(0, $totalPerKid - $totalPaid), 2);

    if (abs($remaining) < 0.01) {

        return ['payment_status' => 'paid', 'remaining' => 0.0];

    }

    if ($totalPaid > 0.01) {

        return ['payment_status' => 'partial', 'remaining' => $remaining];

    }

    return ['payment_status' => 'pending', 'remaining' => $remaining];

}



function enrichWaitlistRow(array $row, $totalPerKid)

{

    $history = parseWaitlistPaymentHistory($row['payment_history'] ?? null);

    $totals = computeWaitlistPaymentTotals($history);

    $deposit = floatval($row['deposit'] ?? 0);

    $donationField = floatval($row['donation'] ?? 0);



    if (empty($history) && $deposit > 0) {

        $totals['total_paid'] = $deposit;

        $totals['total_donation'] = $donationField;

    } elseif ($totals['total_paid'] < $deposit - 0.01) {

        $totals['total_paid'] = $deposit;

    }

    if ($totals['total_donation'] < $donationField - 0.01) {

        $totals['total_donation'] = $donationField;

    }



    $status = tripPaymentStatusFromPaid($totals['total_paid'], $totalPerKid);

    $row['payment_history'] = $history;

    $row['total_paid'] = $totals['total_paid'];

    $row['total_donation'] = $totals['total_donation'];

    $row['deposit'] = $totals['total_paid'];

    $row['donation'] = $totals['total_donation'];

    $row['remaining'] = $status['remaining'];

    $row['payment_status'] = $status['payment_status'];

    return $row;

}



function fetchRegistrationPaymentsAsHistory($conn, $registrationId)

{

    $history = [];

    $stmt = $conn->prepare("

        SELECT id, amount, donation, payment_method, received_by, notes, payment_date, is_deleted

        FROM trip_payments

        WHERE registration_id = ?

        ORDER BY id ASC

    ");

    if (!$stmt) {

        return $history;

    }

    $stmt->bind_param("i", $registrationId);

    $stmt->execute();

    $result = $stmt->get_result();

    while ($p = $result->fetch_assoc()) {

        if (intval($p['is_deleted'] ?? 0) === 1) {

            continue;

        }

        $amount = floatval($p['amount'] ?? 0);

        $donation = floatval($p['donation'] ?? 0);

        $type = 'payment';

        if ($amount > 0 && strpos((string) ($p['notes'] ?? ''), 'دفعة مقدمة') !== false) {

            $type = 'deposit';

        } elseif ($amount <= 0 && $donation > 0) {

            $type = 'donation';

        }

        $history[] = [

            'id' => $p['id'],

            'type' => $type,

            'timestamp' => $p['payment_date'] ?? date('c'),

            'amount' => $amount,

            'donation' => $donation,

            'payment_method' => $p['payment_method'] ?? 'cash',

            'received_by' => $p['received_by'],

            'notes' => $p['notes'] ?? '',

            'is_deleted' => 0,

        ];

    }

    return $history;

}



function normalizeTripPaymentDate($raw)

{

    if (empty($raw)) {

        return date('Y-m-d H:i:s');

    }

    $ts = strtotime($raw);

    if ($ts === false) {

        return date('Y-m-d H:i:s');

    }

    return date('Y-m-d H:i:s', $ts);

}



function syncTripRegistrationPaymentStatus($conn, $registrationId, $totalPerKid)

{

    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total_paid FROM trip_payments WHERE registration_id = ? AND is_deleted = 0");

    $stmt->bind_param("i", $registrationId);

    $stmt->execute();

    $totalPaid = floatval($stmt->get_result()->fetch_assoc()['total_paid'] ?? 0);

    $status = tripPaymentStatusFromPaid($totalPaid, $totalPerKid);

    $sync = $conn->prepare("UPDATE trip_registrations SET payment_status = ? WHERE id = ?");

    $sync->bind_param("si", $status['payment_status'], $registrationId);

    $sync->execute();

    return $status;

}



function getWaitlistNextPosition($conn, $tripId)

{

    $stmt = $conn->prepare("SELECT COALESCE(MAX(position), 0) + 1 AS next_pos FROM trip_waitlist WHERE trip_id = ?");

    $stmt->bind_param("i", $tripId);

    $stmt->execute();

    return (int) $stmt->get_result()->fetch_assoc()['next_pos'];

}



function promoteFirstFromWaitlist($conn, $tripId, $churchId)

{

    // Get the first student in the waitlist

    $stmt = $conn->prepare("

        SELECT tw.*, s.name AS student_name, s.image_url AS student_image

        FROM trip_waitlist tw

        JOIN students s ON s.id = tw.student_id

        WHERE tw.trip_id = ?

        ORDER BY tw.position ASC

        LIMIT 1

    ");

    $stmt->bind_param("i", $tripId);

    $stmt->execute();

    $waiting = $stmt->get_result()->fetch_assoc();

    if (!$waiting) {

        return null;

    }



    $totalPerKid = getTripTotalPerKid($conn, $tripId);

    $waiting = enrichWaitlistRow($waiting, $totalPerKid);



    $deposit = floatval($waiting['total_paid'] ?? $waiting['deposit'] ?? 0);

    $notes = $waiting['notes'] ?? '';

    $customData = $waiting['custom_data'];

    $addedBy = $waiting['added_by'];

    $studentId = intval($waiting['student_id']);

    $historyArray = parseWaitlistPaymentHistory($waiting['payment_history'] ?? null);

    $paymentsToInsert = [];



    foreach ($historyArray as $p) {

        if (intval($p['is_deleted'] ?? 0) === 1) {

            continue;

        }

        $paymentsToInsert[] = $p;

    }



    $historyPaid = 0.0;

    foreach ($paymentsToInsert as $p) {

        if (($p['type'] ?? '') !== 'donation') {

            $historyPaid += floatval($p['amount'] ?? 0);

        }

    }



    if ($deposit > $historyPaid + 0.01) {

        $paymentsToInsert[] = [

            'type' => 'deposit',

            'timestamp' => date('c'),

            'amount' => round($deposit - $historyPaid, 2),

            'donation' => 0,

            'payment_method' => 'cash',

            'received_by' => $addedBy,

            'notes' => 'دفعة مقدمة - ترقية من قائمة الانتظار',

            'is_deleted' => 0,

        ];

    } elseif (empty($paymentsToInsert) && $deposit > 0.01) {

        $paymentsToInsert[] = [

            'type' => 'deposit',

            'timestamp' => date('c'),

            'amount' => $deposit,

            'donation' => 0,

            'payment_method' => 'cash',

            'received_by' => $addedBy,

            'notes' => 'دفعة مقدمة - ترقية من قائمة الانتظار',

            'is_deleted' => 0,

        ];

    }



    $paymentStatus = $waiting['payment_status'] ?? 'pending';



    // Remove any old cancelled registration for this student on this trip

    $del = $conn->prepare("DELETE FROM trip_registrations WHERE trip_id = ? AND student_id = ? AND cancelled = 1");

    $del->bind_param("ii", $tripId, $studentId);

    $del->execute();



    // Insert a normal registration (preserve deposit + payment status from waitlist)

    $ins = $conn->prepare("

        INSERT INTO trip_registrations (trip_id, student_id, registered_by, deposit, notes, custom_data, payment_status)

        VALUES (?, ?, ?, ?, ?, ?, ?)

    ");

    $ins->bind_param("iiidsss", $tripId, $studentId, $addedBy, $deposit, $notes, $customData, $paymentStatus);

    if (!$ins->execute()) {

        return null;

    }



    $registrationId = $conn->insert_id;

    $newHistoryArray = [];



    foreach ($paymentsToInsert as $p) {

        $pAmt = floatval($p['amount'] ?? 0);

        $pDon = floatval($p['donation'] ?? 0);

        $pMethod = $p['payment_method'] ?? 'cash';

        $pNotes = $p['notes'] ?? '';

        $pDate = normalizeTripPaymentDate($p['timestamp'] ?? null);

        $receivedBy = intval($p['received_by'] ?? $addedBy ?? 0);

        if ($receivedBy <= 0) {

            $receivedBy = intval($addedBy ?? 0);

        }



        $ps = $conn->prepare("

            INSERT INTO trip_payments (registration_id, amount, donation, payment_method, received_by, notes, payment_date)

            VALUES (?, ?, ?, ?, ?, ?, ?)

        ");

        $ps->bind_param("iddsiss", $registrationId, $pAmt, $pDon, $pMethod, $receivedBy, $pNotes, $pDate);

        $ps->execute();

        $realPaymentId = $conn->insert_id;



        $newHistoryArray[] = [

            'id' => $realPaymentId,

            'type' => $p['type'] ?? (($pAmt > 0) ? 'payment' : 'donation'),

            'timestamp' => $pDate,

            'amount' => $pAmt,

            'donation' => $pDon,

            'payment_method' => $pMethod,

            'received_by' => $receivedBy,

            'notes' => $pNotes,

            'is_deleted' => 0,

        ];

    }



    $updatedHistoryJson = json_encode($newHistoryArray, JSON_UNESCAPED_UNICODE);

    $updHist = $conn->prepare("UPDATE trip_registrations SET payment_history = ? WHERE id = ?");

    $updHist->bind_param("si", $updatedHistoryJson, $registrationId);

    $updHist->execute();



    syncTripRegistrationPaymentStatus($conn, $registrationId, $totalPerKid);



    // Remove from waitlist

    $delW = $conn->prepare("DELETE FROM trip_waitlist WHERE trip_id = ? AND student_id = ?");

    $delW->bind_param("ii", $tripId, $studentId);

    $delW->execute();



    // Re-number remaining positions

    $conn->query("SET @pos := 0");

    $upd = $conn->prepare("UPDATE trip_waitlist SET position = (@pos := @pos + 1) WHERE trip_id = ? ORDER BY position ASC");

    $upd->bind_param("i", $tripId);

    $upd->execute();



    return [

        'student_id' => $studentId,

        'student_name' => $waiting['student_name'],

        'student_image' => $waiting['student_image'],

        'registration_id' => $registrationId,

        'total_paid' => $deposit,

        'payment_status' => $paymentStatus,

    ];

}



function getWaitlistData($tripId, $conn)

{

    ensureWaitlistTable($conn);

    $totalPerKid = getTripTotalPerKid($conn, $tripId);

    $stmt = $conn->prepare("

        SELECT tw.*, s.name AS student_name, s.phone AS student_phone, s.image_url AS student_image,

               COALESCE(cc.arabic_name, cl.arabic_name, s.class) AS student_class

        FROM trip_waitlist tw

        JOIN students s ON s.id = tw.student_id

        LEFT JOIN church_classes cc ON cc.id = s.class_id AND cc.church_id = s.church_id

        LEFT JOIN classes cl ON cl.id = s.class_id

        WHERE tw.trip_id = ?

        ORDER BY tw.position ASC

    ");

    $stmt->bind_param("i", $tripId);

    $stmt->execute();

    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $enriched = [];

    foreach ($rows as $row) {

        $enriched[] = enrichWaitlistRow($row, $totalPerKid);

    }

    return $enriched;

}



function getWaitlistAction()

{

    try {

        checkAuth();

        $tripId = intval($_POST['trip_id'] ?? 0);

        if (!$tripId) {

            sendJSON(['success' => false, 'message' => 'trip_id مطلوب']);

            return;

        }

        $conn = getDBConnection();

        ensureWaitlistTable($conn);

        $list = getWaitlistData($tripId, $conn);

        sendJSON(['success' => true, 'waitlist' => $list, 'count' => count($list)]);

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



function removeFromWaitlist()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $tripId = intval($_POST['trip_id'] ?? 0);

        $studentId = intval($_POST['student_id'] ?? 0);

        if (!$tripId || !$studentId) {

            sendJSON(['success' => false, 'message' => 'بيانات ناقصة']);

            return;

        }

        $conn = getDBConnection();

        ensureWaitlistTable($conn);

        $stmt = $conn->prepare("DELETE FROM trip_waitlist WHERE trip_id = ? AND student_id = ? AND church_id = ?");

        $stmt->bind_param("iii", $tripId, $studentId, $churchId);

        $stmt->execute();

        // Re-number

        $conn->query("SET @pos := 0");

        $upd = $conn->prepare("UPDATE trip_waitlist SET position = (@pos := @pos + 1) WHERE trip_id = ? ORDER BY position ASC");

        $upd->bind_param("i", $tripId);

        $upd->execute();

        sendJSON(['success' => true, 'message' => 'تمت إزالة الطفل من قائمة الانتظار']);

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



function addTripWaitlistPayment()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $uncleId = $_SESSION['uncle_id'] ?? null;



        $tripId = intval($_POST['trip_id'] ?? 0);

        $studentId = intval($_POST['student_id'] ?? 0);

        $amount = floatval($_POST['amount'] ?? 0);

        $donation = floatval($_POST['donation'] ?? 0);

        $paymentMethod = sanitize($_POST['payment_method'] ?? 'cash');

        $notes = sanitize($_POST['notes'] ?? '');



        if ($tripId === 0 || $studentId === 0 || ($amount <= 0 && $donation <= 0)) {

            sendJSON(['success' => false, 'message' => 'بيانات الدفع أو التبرع غير صحيحة']);

            return;

        }



        $conn = getDBConnection();

        ensureWaitlistTable($conn);



        if (!verifyTripParticipant($conn, $tripId, $churchId)) {

            sendJSON(['success' => false, 'message' => 'الرحلة غير موجودة أو غير مصرح لك بإضافة دفعة']);

            return;

        }



        // Fetch waitlist record

        $stmt = $conn->prepare("SELECT * FROM trip_waitlist WHERE trip_id = ? AND student_id = ? AND church_id = ?");

        $stmt->bind_param("iii", $tripId, $studentId, $churchId);

        $stmt->execute();

        $waitRecord = $stmt->get_result()->fetch_assoc();

        if (!$waitRecord) {

            sendJSON(['success' => false, 'message' => 'الطفل غير مسجل في قائمة الانتظار']);

            return;

        }



        $totalPerKid = getTripTotalPerKid($conn, $tripId);

        $historyArray = parseWaitlistPaymentHistory($waitRecord['payment_history'] ?? null);

        $totals = computeWaitlistPaymentTotals($historyArray);

        $totalPaid = $totals['total_paid'];

        if ($totalPaid < floatval($waitRecord['deposit'] ?? 0) - 0.01) {

            $totalPaid = floatval($waitRecord['deposit'] ?? 0);

        }



        $remaining = $totalPerKid - $totalPaid;

        if ($amount > $remaining) {

            sendJSON(['success' => false, 'message' => 'المبلغ أكبر من المتبقي']);

            return;

        }



        // Add payment to history

        $newPaymentId = time() . rand(100, 999);

        $historyArray[] = [

            'id' => $newPaymentId,

            'type' => $amount > 0 ? 'payment' : 'donation',

            'timestamp' => date('c'),

            'amount' => round($amount, 2),

            'donation' => round($donation, 2),

            'payment_method' => $paymentMethod,

            'received_by' => $uncleId,

            'notes' => $notes,

            'is_deleted' => 0

        ];



        // Recalculate totals

        $newTotalPaid = 0;

        $newTotalDonation = 0;

        foreach ($historyArray as $p) {

            if (intval($p['is_deleted'] ?? 0) === 0) {

                if (($p['type'] ?? '') === 'donation') {

                    $newTotalDonation += floatval($p['donation'] ?? 0);

                } else {

                    $newTotalPaid += floatval($p['amount'] ?? 0);

                }

            }

        }



        $paymentHistoryJson = json_encode($historyArray, JSON_UNESCAPED_UNICODE);



        // Update waitlist record

        $upd = $conn->prepare("UPDATE trip_waitlist SET deposit = ?, donation = ?, payment_history = ? WHERE id = ?");

        $upd->bind_param("ddsi", $newTotalPaid, $newTotalDonation, $paymentHistoryJson, $waitRecord['id']);



        if ($upd->execute()) {

            $status = tripPaymentStatusFromPaid($newTotalPaid, $totalPerKid);



            sendJSON([

                'success' => true,

                'message' => 'تم إضافة الدفعة لقائمة الانتظار بنجاح',

                'payment_id' => $newPaymentId,

                'new_status' => $status['payment_status'],

                'total_paid' => $newTotalPaid,

                'remaining' => $status['remaining']

            ]);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في إضافة الدفعة لقائمة الانتظار']);

        }



    } catch (Exception $e) {

        error_log("addTripWaitlistPayment error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في إضافة الدفعة لقائمة الانتظار: ' . $e->getMessage()]);

    }

}



function deleteTripWaitlistPayment()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $uncleId = $_SESSION['uncle_id'] ?? null;



        $paymentId = $_POST['payment_id'] ?? '';

        $studentId = intval($_POST['student_id'] ?? 0);

        $tripId = intval($_POST['trip_id'] ?? 0);



        if (!$paymentId || !$studentId || !$tripId) {

            sendJSON(['success' => false, 'message' => 'بيانات غير مكتملة']);

            return;

        }



        $conn = getDBConnection();

        ensureWaitlistTable($conn);



        if (!verifyTripParticipant($conn, $tripId, $churchId)) {

            sendJSON(['success' => false, 'message' => 'الرحلة غير موجودة أو غير مصرح لك بحذف دفعة']);

            return;

        }



        // Fetch waitlist record

        $stmt = $conn->prepare("SELECT * FROM trip_waitlist WHERE trip_id = ? AND student_id = ? AND church_id = ?");

        $stmt->bind_param("iii", $tripId, $studentId, $churchId);

        $stmt->execute();

        $waitRecord = $stmt->get_result()->fetch_assoc();

        if (!$waitRecord) {

            sendJSON(['success' => false, 'message' => 'الطفل غير مسجل في قائمة الانتظار']);

            return;

        }



        $historyArray = json_decode($waitRecord['payment_history'] ?? '[]', true) ?: [];

        $found = false;



        foreach ($historyArray as &$p) {

            if (isset($p['id']) && $p['id'] == $paymentId) {

                $p['is_deleted'] = 1;

                $p['deleted_at'] = date('c');

                $p['deleted_by'] = $uncleId;

                $found = true;

                break;

            }

        }



        if (!$found) {

            sendJSON(['success' => false, 'message' => 'الدفعة غير موجودة في سجل قائمة الانتظار']);

            return;

        }



        // Recalculate totals

        $newTotalPaid = 0;

        $newTotalDonation = 0;

        foreach ($historyArray as $p) {

            if (intval($p['is_deleted'] ?? 0) === 0) {

                if (($p['type'] ?? '') === 'donation') {

                    $newTotalDonation += floatval($p['donation'] ?? 0);

                } else {

                    $newTotalPaid += floatval($p['amount'] ?? 0);

                }

            }

        }



        $paymentHistoryJson = json_encode($historyArray, JSON_UNESCAPED_UNICODE);



        // Update waitlist record

        $upd = $conn->prepare("UPDATE trip_waitlist SET deposit = ?, donation = ?, payment_history = ? WHERE id = ?");

        $upd->bind_param("ddsi", $newTotalPaid, $newTotalDonation, $paymentHistoryJson, $waitRecord['id']);



        if ($upd->execute()) {

            $totalPerKid = getTripTotalPerKid($conn, $tripId);

            $status = tripPaymentStatusFromPaid($newTotalPaid, $totalPerKid);



            sendJSON([

                'success' => true,

                'message' => 'تم حذف الدفعة بنجاح',

                'new_status' => $status['payment_status'],

                'total_paid' => $newTotalPaid,

                'remaining' => $status['remaining']

            ]);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في حذف الدفعة']);

        }



    } catch (Exception $e) {

        error_log("deleteTripWaitlistPayment error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في حذف الدفعة: ' . $e->getMessage()]);

    }

}



function restoreTripWaitlistPayment()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $uncleId = $_SESSION['uncle_id'] ?? null;



        $paymentId = $_POST['payment_id'] ?? '';

        $studentId = intval($_POST['student_id'] ?? 0);

        $tripId = intval($_POST['trip_id'] ?? 0);



        if (!$paymentId || !$studentId || !$tripId) {

            sendJSON(['success' => false, 'message' => 'بيانات غير مكتملة']);

            return;

        }



        $conn = getDBConnection();

        ensureWaitlistTable($conn);



        if (!verifyTripParticipant($conn, $tripId, $churchId)) {

            sendJSON(['success' => false, 'message' => 'الرحلة غير موجودة أو غير مصرح لك باستعادة دفعة']);

            return;

        }



        // Fetch waitlist record

        $stmt = $conn->prepare("SELECT * FROM trip_waitlist WHERE trip_id = ? AND student_id = ? AND church_id = ?");

        $stmt->bind_param("iii", $tripId, $studentId, $churchId);

        $stmt->execute();

        $waitRecord = $stmt->get_result()->fetch_assoc();

        if (!$waitRecord) {

            sendJSON(['success' => false, 'message' => 'الطفل غير مسجل في قائمة الانتظار']);

            return;

        }



        $historyArray = json_decode($waitRecord['payment_history'] ?? '[]', true) ?: [];

        $found = false;



        foreach ($historyArray as &$p) {

            if (isset($p['id']) && $p['id'] == $paymentId) {

                $p['is_deleted'] = 0;

                unset($p['deleted_at']);

                unset($p['deleted_by']);

                $found = true;

                break;

            }

        }



        if (!$found) {

            sendJSON(['success' => false, 'message' => 'الدفعة غير موجودة في سجل قائمة الانتظار']);

            return;

        }



        // Recalculate totals

        $newTotalPaid = 0;

        $newTotalDonation = 0;

        foreach ($historyArray as $p) {

            if (intval($p['is_deleted'] ?? 0) === 0) {

                if (($p['type'] ?? '') === 'donation') {

                    $newTotalDonation += floatval($p['donation'] ?? 0);

                } else {

                    $newTotalPaid += floatval($p['amount'] ?? 0);

                }

            }

        }



        $paymentHistoryJson = json_encode($historyArray, JSON_UNESCAPED_UNICODE);



        // Update waitlist record

        $upd = $conn->prepare("UPDATE trip_waitlist SET deposit = ?, donation = ?, payment_history = ? WHERE id = ?");

        $upd->bind_param("ddsi", $newTotalPaid, $newTotalDonation, $paymentHistoryJson, $waitRecord['id']);



        if ($upd->execute()) {

            $totalPerKid = getTripTotalPerKid($conn, $tripId);

            $status = tripPaymentStatusFromPaid($newTotalPaid, $totalPerKid);



            sendJSON([

                'success' => true,

                'message' => 'تم استعادة الدفعة بنجاح',

                'new_status' => $status['payment_status'],

                'total_paid' => $newTotalPaid,

                'remaining' => $status['remaining']

            ]);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في استعادة الدفعة']);

        }



    } catch (Exception $e) {

        error_log("restoreTripWaitlistPayment error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في استعادة الدفعة: ' . $e->getMessage()]);

    }

}



// ── END WAITLIST HELPERS ──────────────────────────────────────────────────────



function rebalanceTripWaitlist()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $tripId = intval($_POST['trip_id'] ?? 0);

        if (!$tripId) {

            sendJSON(['success' => false, 'message' => 'trip_id مطلوب']);

            return;

        }



        $conn = getDBConnection();

        ensureWaitlistTable($conn);



        if (!verifyTripParticipant($conn, $tripId, $churchId)) {

            sendJSON(['success' => false, 'message' => 'الرحلة غير موجودة أو غير مصرح لك بموازنتها']);

            return;

        }



        // Get trip max

        $tripStmt = $conn->prepare("SELECT max_participants FROM trips WHERE id = ?");

        $tripStmt->bind_param("i", $tripId);

        $tripStmt->execute();

        $trip = $tripStmt->get_result()->fetch_assoc();

        if (!$trip) {

            sendJSON(['success' => false, 'message' => 'الرحلة غير موجودة']);

            return;

        }

        $max = intval($trip['max_participants']);

        if ($max <= 0) {

            sendJSON(['success' => true, 'message' => 'لا يوجد حد أقصى للرحلة، لا حاجة للموازنة', 'moved' => 0]);

            return;

        }



        // Get all active registrations ordered by creation date (earliest first stay in)

        $regStmt = $conn->prepare("SELECT id, student_id, deposit, notes, custom_data, created_at, payment_history FROM trip_registrations WHERE trip_id = ? AND cancelled = 0 ORDER BY created_at ASC, id ASC");

        $regStmt->bind_param("i", $tripId);

        $regStmt->execute();

        $regs = $regStmt->get_result()->fetch_all(MYSQLI_ASSOC);



        if (count($regs) <= $max) {

            sendJSON(['success' => true, 'message' => 'الرحلة لم تتخطى الحد الأقصى بعد', 'moved' => 0]);

            return;

        }



        $extra = array_slice($regs, $max);

        $movedCount = 0;



        foreach ($extra as $r) {

            $regId = $r['id'];

            $studentId = $r['student_id'];

            $historyArray = parseWaitlistPaymentHistory($r['payment_history'] ?? null);

            if (empty($historyArray)) {

                $historyArray = fetchRegistrationPaymentsAsHistory($conn, $regId);

            }

            $totals = computeWaitlistPaymentTotals($historyArray);

            $totalPaid = $totals['total_paid'];

            $totalDonation = $totals['total_donation'];

            if ($totalPaid < floatval($r['deposit'] ?? 0) - 0.01) {

                $totalPaid = floatval($r['deposit'] ?? 0);

            }

            $paymentHistoryJson = json_encode($historyArray, JSON_UNESCAPED_UNICODE);



            // Move to waitlist

            $pos = getWaitlistNextPosition($conn, $tripId);

            $ins = $conn->prepare("INSERT INTO trip_waitlist (trip_id, student_id, church_id, position, notes, deposit, donation, custom_data, payment_history, added_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $ins->bind_param("iiiisddsss", $tripId, $studentId, $churchId, $pos, $r['notes'], $totalPaid, $totalDonation, $r['custom_data'], $paymentHistoryJson, $r['created_at']);



            if ($ins->execute()) {

                // Delete payments (they are consolidated in waitlist.deposit)

                $delP = $conn->prepare("DELETE FROM trip_payments WHERE registration_id = ?");

                $delP->bind_param("i", $regId);

                $delP->execute();



                // Delete the registration

                $del = $conn->prepare("DELETE FROM trip_registrations WHERE id = ?");

                $del->bind_param("i", $regId);

                $del->execute();



                $movedCount++;

            }

        }



        sendJSON(['success' => true, 'message' => "تم نقل $movedCount طفل إلى قائمة الانتظار بنجاح", 'moved' => $movedCount]);

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



function registerStudentForTrip()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $uncleId = $_SESSION['uncle_id'] ?? null;



        $tripId = intval($_POST['trip_id'] ?? 0);

        $studentId = intval($_POST['student_id'] ?? 0);

        $deposit = floatval($_POST['deposit'] ?? 0);

        $donation = floatval($_POST['donation'] ?? 0);

        $notes = sanitize($_POST['notes'] ?? '');



        // Sanitize custom_data: decode JSON, sanitize each key/value, re-encode.

        $customData = null;

        if (!empty($_POST['custom_data'])) {

            $rawCd = json_decode($_POST['custom_data'], true);

            if (is_array($rawCd)) {

                $cleanCd = [];

                foreach ($rawCd as $cdKey => $cdVal) {

                    $cleanKey = strip_tags(trim((string) $cdKey));

                    $cleanVal = strip_tags(trim((string) $cdVal));

                    if ($cleanKey !== '' && $cleanVal !== '') {

                        $cleanCd[$cleanKey] = $cleanVal;

                    }

                }

                if (!empty($cleanCd)) {

                    $customData = json_encode($cleanCd, JSON_UNESCAPED_UNICODE);

                }

            }

        }



        $regType = sanitize($_POST['registration_type'] ?? 'student');

        if (!in_array($regType, ['student', 'other_church_student', 'guest'])) {

            $regType = 'student';

        }



        if ($tripId === 0 || ($regType !== 'guest' && $studentId === 0)) {

            sendJSON(['success' => false, 'message' => 'الرحلة والطفل مطلوبان']);

            return;

        }



        $conn = getDBConnection();

        ensureWaitlistTable($conn);

        ensureGuestsTable($conn);



        // Validations for student registrations (either local or other-church)

        if ($regType !== 'guest') {

            // التحقق من وجود الطفل وأن كنيسته تشارك في هذه الرحلة

            $checkStudent = $conn->prepare("SELECT id, church_id FROM students WHERE id = ?");

            $checkStudent->bind_param("i", $studentId);

            $checkStudent->execute();

            $studentRes = $checkStudent->get_result()->fetch_assoc();

            if (!$studentRes) {

                sendJSON(['success' => false, 'message' => 'الطفل غير موجود']);

                return;

            }

            $studentChurchId = intval($studentRes['church_id']);



            if ($regType === 'student' && !verifyTripParticipant($conn, $tripId, $studentChurchId)) {

                sendJSON(['success' => false, 'message' => 'كنيسة الطفل لا تشارك في هذه الرحلة']);

                return;

            }



            // التحقق من عدم تسجيل الطفل مسبقاً (نشط)

            $checkReg = $conn->prepare("SELECT id, cancelled FROM trip_registrations WHERE trip_id = ? AND student_id = ?");

            $checkReg->bind_param("ii", $tripId, $studentId);

            $checkReg->execute();

            $regResult = $checkReg->get_result();

            if ($regResult->num_rows > 0) {

                $existing = $regResult->fetch_assoc();

                if ($existing['cancelled'] == 0) {

                    sendJSON(['success' => false, 'message' => 'الطفل مسجل بالفعل في هذه الرحلة']);

                    return;

                }

            }



            // التحقق من عدم وجوده في قائمة الانتظار

            $checkWait = $conn->prepare("SELECT id FROM trip_waitlist WHERE trip_id = ? AND student_id = ?");

            $checkWait->bind_param("ii", $tripId, $studentId);

            $checkWait->execute();

            if ($checkWait->get_result()->num_rows > 0) {

                sendJSON(['success' => false, 'message' => 'الطفل موجود بالفعل في قائمة الانتظار']);

                return;

            }

        }



        // تحقق من الحد الأقصى للمشاركين

        if (!verifyTripParticipant($conn, $tripId, $churchId)) {

            sendJSON(['success' => false, 'message' => 'الرحلة غير موجودة أو غير مصرح لك بالتسجيل بها']);

            return;

        }



        $tripStmt = $conn->prepare("SELECT max_participants, title FROM trips WHERE id = ?");

        $tripStmt->bind_param("i", $tripId);

        $tripStmt->execute();

        $trip = $tripStmt->get_result()->fetch_assoc();

        if (!$trip) {

            sendJSON(['success' => false, 'message' => 'الرحلة غير موجودة']);

            return;

        }



        $totalPerKid = getTripTotalPerKid($conn, $tripId);

        if ($deposit > $totalPerKid) {

            sendJSON(['success' => false, 'message' => 'المبلغ أكبر من سعر الرحلة']);

            return;

        }



        $maxParticipants = intval($trip['max_participants'] ?? 0);



        // عدد المسجلين النشطين

        $countStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM trip_registrations WHERE trip_id = ? AND cancelled = 0");

        $countStmt->bind_param("i", $tripId);

        $countStmt->execute();

        $activeCount = (int) $countStmt->get_result()->fetch_assoc()['cnt'];



        // إذا الرحلة ممتلئة → أضفه لقائمة الانتظار (فقط للطلاب وليس للزوار)

        if ($regType !== 'guest' && $maxParticipants > 0 && $activeCount >= $maxParticipants) {

            $position = getWaitlistNextPosition($conn, $tripId);

            $historyArray = [];

            if ($deposit > 0) {

                $historyArray[] = [

                    'id' => time() . '1',

                    'type' => 'deposit',

                    'timestamp' => date('c'),

                    'amount' => round($deposit, 2),

                    'donation' => 0,

                    'payment_method' => 'deposit',

                    'received_by' => $uncleId,

                    'notes' => 'دفعة مقدمة للتسجيل',

                    'is_deleted' => 0

                ];

            }

            if ($donation > 0) {

                $historyArray[] = [

                    'id' => time() . '2',

                    'type' => 'donation',

                    'timestamp' => date('c'),

                    'amount' => 0,

                    'donation' => round($donation, 2),

                    'payment_method' => 'donation',

                    'received_by' => $uncleId,

                    'notes' => 'تبرع عند التسجيل',

                    'is_deleted' => 0

                ];

            }

            $paymentHistoryJson = !empty($historyArray) ? json_encode($historyArray, JSON_UNESCAPED_UNICODE) : null;



            $insW = $conn->prepare("INSERT INTO trip_waitlist (trip_id, student_id, church_id, added_by, position, notes, deposit, donation, custom_data, payment_history) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $insW->bind_param("iiiiisddss", $tripId, $studentId, $churchId, $uncleId, $position, $notes, $deposit, $donation, $customData, $paymentHistoryJson);

            if ($insW->execute()) {

                sendJSON([

                    'success' => true,

                    'waitlisted' => true,

                    'position' => $position,

                    'message' => "تم إضافة الطفل إلى قائمة الانتظار (رقم $position) — الرحلة ممتلئة"

                ]);

            } else {

                sendJSON(['success' => false, 'message' => 'فشل في إضافة الطفل لقائمة الانتظار: ' . $insW->error]);

            }

            return;

        }



        // إلغاء أي تسجيل سابق ملغي (فقط للطلاب)

        if ($regType !== 'guest') {

            $deleteStmt = $conn->prepare("DELETE FROM trip_registrations WHERE trip_id = ? AND student_id = ? AND cancelled = 1");

            $deleteStmt->bind_param("ii", $tripId, $studentId);

            $deleteStmt->execute();

        }



        // تسجيل جديد

        $stmt = $conn->prepare("

            INSERT INTO trip_registrations (

                trip_id, student_id, registered_by, deposit, notes, custom_data,

                registration_type, guest_id

            )

            VALUES (?, ?, ?, ?, ?, ?, ?, ?)

        ");



        $dbStudentId = ($regType === 'guest') ? null : $studentId;

        $dbGuestId = null;



        if ($regType === 'guest') {

            // Either use existing guest_id or create a new guest inline

            $dbGuestId = intval($_POST['guest_id'] ?? 0);

            if ($dbGuestId === 0) {

                // Create new guest record

                $guestName = sanitize($_POST['guest_name'] ?? '');

                $guestPhone = sanitize($_POST['guest_phone'] ?? '');

                $guestGuardianName = sanitize($_POST['guest_guardian_name'] ?? '');

                $guestClass = sanitize($_POST['guest_class'] ?? '');

                $guestGender = sanitize($_POST['guest_gender'] ?? '');



                if (empty($guestName)) {

                    sendJSON(['success' => false, 'message' => 'اسم الزائر مطلوب']);

                    return;

                }



                ensureGuestsTable($conn);

                $gInsert = $conn->prepare("INSERT INTO guests (church_id, name, phone, guardian_name, `class`, gender, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");

                $gPhone = $guestPhone ?: null;

                $gGuardian = $guestGuardianName ?: null;

                $gClass = $guestClass ?: null;

                $gGender = in_array($guestGender, ['male', 'female']) ? $guestGender : null;

                $gInsert->bind_param("isssssi", $churchId, $guestName, $gPhone, $gGuardian, $gClass, $gGender, $uncleId);

                $gInsert->execute();

                $dbGuestId = $conn->insert_id;

            }

        }



        $stmt->bind_param(

            "iiidsssi",

            $tripId,

            $dbStudentId,

            $uncleId,

            $deposit,

            $notes,

            $customData,

            $regType,

            $dbGuestId

        );



        if ($stmt->execute()) {

            $registrationId = $conn->insert_id;

            $historyArray = [];



            // إذا كان هناك دفعة مقدمة، أضفها كدفعة

            if ($deposit > 0) {

                $paymentStmt = $conn->prepare("

                    INSERT INTO trip_payments (registration_id, amount, received_by, notes)

                    VALUES (?, ?, ?, 'دفعة مقدمة للتسجيل')

                ");

                $paymentStmt->bind_param("idi", $registrationId, $deposit, $uncleId);

                $paymentStmt->execute();

                $depositPaymentId = $conn->insert_id;



                $historyArray[] = [

                    'id' => $depositPaymentId,

                    'type' => 'deposit',

                    'timestamp' => date('c'),

                    'amount' => round($deposit, 2),

                    'donation' => 0,

                    'payment_method' => 'deposit',

                    'received_by' => $uncleId,

                    'notes' => 'دفعة مقدمة للتسجيل',

                    'is_deleted' => 0

                ];

            }



            if ($donation > 0) {

                $donationStmt = $conn->prepare("

                    INSERT INTO trip_payments (registration_id, amount, donation, payment_method, received_by, notes)

                    VALUES (?, 0, ?, 'donation', ?, ?)

                ");

                $donationStmt->bind_param("idis", $registrationId, $donation, $uncleId, $notes);

                $donationStmt->execute();

                $donationPaymentId = $conn->insert_id;



                $historyArray[] = [

                    'id' => $donationPaymentId,

                    'type' => 'donation',

                    'timestamp' => date('c'),

                    'amount' => 0,

                    'donation' => round($donation, 2),

                    'payment_method' => 'donation',

                    'received_by' => $uncleId,

                    'notes' => 'تبرع عند التسجيل'

                ];

            }



            if (!empty($historyArray)) {

                $historyJson = json_encode($historyArray, JSON_UNESCAPED_UNICODE);

                $historyStmt = $conn->prepare("UPDATE trip_registrations SET payment_history = ? WHERE id = ?");

                $historyStmt->bind_param("si", $historyJson, $registrationId);

                $historyStmt->execute();

            }



            // تسجيل النشاط

            logActivity($churchId, $uncleId, 'register_trip', "تسجيل طفل في رحلة ID: $tripId");



            // ► AUDIT

            $tripInfo = getTripSnapshot($tripId);

            $auditStudentId = ($regType === 'guest') ? 0 : $studentId;

            $auditStudentName = ($regType === 'guest') ? (sanitize($_POST['guest_name'] ?? '') ?: 'زائر') : '';

            auditTripRegistration($tripId, $tripInfo['title'] ?? '', $auditStudentId, $auditStudentName, 'register');



            sendJSON([

                'success' => true,

                'waitlisted' => false,

                'message' => 'تم تسجيل الطفل في الرحلة بنجاح',

                'registration_id' => $registrationId

            ]);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في تسجيل الطفل: ' . $stmt->error]);

        }



    } catch (Exception $e) {

        error_log("registerStudentForTrip error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تسجيل الطفل: ' . $e->getMessage()]);

    }

}

function addTripPayment()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $uncleId = $_SESSION['uncle_id'] ?? null;



        $registrationId = intval($_POST['registration_id'] ?? 0);

        $amount = floatval($_POST['amount'] ?? 0);

        $paymentMethod = sanitize($_POST['payment_method'] ?? 'cash');

        $notes = sanitize($_POST['notes'] ?? '');



        $donation = floatval($_POST['donation'] ?? 0);



        if ($registrationId === 0 || ($amount <= 0 && $donation <= 0)) {

            sendJSON(['success' => false, 'message' => 'بيانات الدفع أو التبرع غير صحيحة']);

            return;

        }



        $conn = getDBConnection();



        if (!verifyRegistrationParticipant($conn, $registrationId, $churchId)) {

            sendJSON(['success' => false, 'message' => 'التسجيل غير موجود أو غير مصرح لك بإضافة دفعة له']);

            return;

        }



        // التحقق من صحة التسجيل

        $checkStmt = $conn->prepare("

            SELECT tr.*, t.price, t.discount, t.discount_type

            FROM trip_registrations tr

            JOIN trips t ON tr.trip_id = t.id

            WHERE tr.id = ?

        ");

        $checkStmt->bind_param("i", $registrationId);

        $checkStmt->execute();

        $result = $checkStmt->get_result();



        if ($result->num_rows === 0) {

            sendJSON(['success' => false, 'message' => 'التسجيل غير موجود']);

            return;

        }



        $registration = $result->fetch_assoc();



        // حساب المبلغ المتبقي

        $finalPrice = $registration['price'];

        if ($registration['discount'] > 0) {

            if ($registration['discount_type'] === 'percentage') {

                $finalPrice = $registration['price'] - ($registration['price'] * $registration['discount'] / 100);

            } else {

                $finalPrice = max(0, $registration['price'] - $registration['discount']);

            }

        }



        $paidStmt = $conn->prepare("SELECT SUM(amount) as total FROM trip_payments WHERE registration_id = ? AND is_deleted = 0");

        $paidStmt->bind_param("i", $registrationId);

        $paidStmt->execute();

        $paidResult = $paidStmt->get_result();

        $paidData = $paidResult->fetch_assoc();

        $totalPaid = floatval($paidData['total'] ?? 0);



        $remaining = $finalPrice - $totalPaid;



        if ($amount > $remaining) {

            sendJSON(['success' => false, 'message' => 'المبلغ أكبر من المتبقي']);

            return;

        }



        // إضافة الدفعة

        $paymentStmt = $conn->prepare("

            INSERT INTO trip_payments (registration_id, amount, donation, payment_method, received_by, notes)

            VALUES (?, ?, ?, ?, ?, ?)

        ");

        $paymentStmt->bind_param("iddsss", $registrationId, $amount, $donation, $paymentMethod, $uncleId, $notes);



        if ($paymentStmt->execute()) {

            $newPaymentId = $conn->insert_id;



            $historyStmt = $conn->prepare("SELECT payment_history FROM trip_registrations WHERE id = ?");

            $historyStmt->bind_param("i", $registrationId);

            $historyStmt->execute();

            $historyData = $historyStmt->get_result()->fetch_assoc();

            $historyArray = json_decode($historyData['payment_history'] ?? '[]', true) ?: [];

            $historyArray[] = [

                'id' => $newPaymentId,

                'type' => 'payment',

                'timestamp' => date('c'),

                'amount' => round($amount, 2),

                'donation' => round($donation, 2),

                'payment_method' => $paymentMethod,

                'received_by' => $uncleId,

                'notes' => $notes,

            ];

            $updatedHistoryJson = json_encode($historyArray, JSON_UNESCAPED_UNICODE);

            $updateHistoryStmt = $conn->prepare("UPDATE trip_registrations SET payment_history = ? WHERE id = ?");

            $updateHistoryStmt->bind_param("si", $updatedHistoryJson, $registrationId);

            $updateHistoryStmt->execute();



            // Recalculate and sync payment_status

            $recalcStmt = $conn->prepare("

        SELECT COALESCE(SUM(amount), 0) as total_paid

        FROM trip_payments WHERE registration_id = ? AND is_deleted = 0

    ");

            $recalcStmt->bind_param("i", $registrationId);

            $recalcStmt->execute();

            $totalPaidNow = floatval($recalcStmt->get_result()->fetch_assoc()['total_paid']);

            $remainingNow = $finalPrice - $totalPaidNow;



            if (abs($remainingNow) < 0.01) {

                $newPaymentStatus = 'paid';

            } elseif ($totalPaidNow > 0.01) {

                $newPaymentStatus = 'partial';

            } else {

                $newPaymentStatus = 'pending';

            }



            $syncStmt = $conn->prepare(

                "UPDATE trip_registrations SET payment_status = ? WHERE id = ?"

            );

            $syncStmt->bind_param("si", $newPaymentStatus, $registrationId);

            $syncStmt->execute();



            sendJSON([

                'success' => true,

                'message' => 'تم إضافة الدفعة بنجاح',

                'payment_id' => $newPaymentId,

                'new_status' => $newPaymentStatus,

                'total_paid' => $totalPaidNow,

                'remaining' => max(0, $remainingNow)

            ]);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في إضافة الدفعة: ' . $paymentStmt->error]);

        }



    } catch (Exception $e) {

        error_log("addTripPayment error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في إضافة الدفعة: ' . $e->getMessage()]);

    }

}

function cancelTripRegistration()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $uncleId = $_SESSION['uncle_id'] ?? null;



        $registrationId = intval($_POST['registration_id'] ?? 0);

        $reason = sanitize($_POST['reason'] ?? '');



        if ($registrationId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف التسجيل مطلوب']);

            return;

        }



        $conn = getDBConnection();

        ensureWaitlistTable($conn);



        // Fetch trip_id and student name before cancelling

        $infoStmt = $conn->prepare("

            SELECT tr.trip_id, s.name AS student_name

            FROM trip_registrations tr

            JOIN students s ON s.id = tr.student_id

            WHERE tr.id = ?

        ");

        $infoStmt->bind_param("i", $registrationId);

        $infoStmt->execute();

        $regInfo = $infoStmt->get_result()->fetch_assoc();

        $tripId = $regInfo ? (int) $regInfo['trip_id'] : 0;



        if (!verifyRegistrationParticipant($conn, $registrationId, $churchId)) {

            sendJSON(['success' => false, 'message' => 'التسجيل غير موجود أو غير مصرح لك بإلغائه']);

            return;

        }



        $stmt = $conn->prepare("

            UPDATE trip_registrations tr

            SET tr.cancelled = 1, tr.cancelled_at = NOW(), tr.cancelled_by = ?

            WHERE tr.id = ?

        ");

        $stmt->bind_param("ii", $uncleId, $registrationId);



        if ($stmt->execute() && $stmt->affected_rows > 0) {

            // ► AUDIT

            writeAuditLog(

                'trip_cancel',

                'trip_registration',

                $registrationId,

                '',

                null,

                ['cancelled' => 1, 'cancelled_by' => $uncleId],

                "إلغاء تسجيل في رحلة — registration ID: $registrationId"

            );



            // Auto-promote first student from waitlist

            $promoted = null;

            if ($tripId > 0) {

                $promoted = promoteFirstFromWaitlist($conn, $tripId, $churchId);

            }



            $response = ['success' => true, 'message' => 'تم إلغاء التسجيل بنجاح'];



            if ($promoted) {

                $response['promoted'] = true;

                $response['promoted_student_name'] = $promoted['student_name'];

                $response['promoted_student_image'] = $promoted['student_image'];

                $response['message'] = "تم إلغاء التسجيل بنجاح ✅\n🎉 تم ترقية {$promoted['student_name']} من قائمة الانتظار تلقائياً!";

            }



            sendJSON($response);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في إلغاء التسجيل أو لم يتم العثور عليه']);

        }



    } catch (Exception $e) {

        error_log("cancelTripRegistration error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في إلغاء التسجيل: ' . $e->getMessage()]);

    }

}



function deleteTripPayment()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $paymentId = intval($_POST['payment_id'] ?? 0);

        $registrationId = intval($_POST['registration_id'] ?? 0);



        if ($paymentId === 0 || $registrationId === 0) {

            sendJSON(['success' => false, 'message' => 'بيانات غير مكتملة']);

            return;

        }



        $conn = getDBConnection();



        $uncleId = $_SESSION['uncle_id'] ?? null;



        if (!verifyRegistrationParticipant($conn, $registrationId, $churchId)) {

            sendJSON(['success' => false, 'message' => 'الدفعة غير موجودة أو غير مصرح بحذفها']);

            return;

        }



        // Verify the payment belongs to the registration

        $checkStmt = $conn->prepare("

            SELECT tp.*, tr.trip_id, t.price, t.discount, t.discount_type

            FROM trip_payments tp

            JOIN trip_registrations tr ON tp.registration_id = tr.id

            JOIN trips t ON tr.trip_id = t.id

            WHERE tp.id = ? AND tr.id = ?

        ");

        $checkStmt->bind_param("ii", $paymentId, $registrationId);

        $checkStmt->execute();

        $res = $checkStmt->get_result();

        if ($res->num_rows === 0) {

            sendJSON(['success' => false, 'message' => 'الدفعة غير موجودة']);

            return;

        }

        $paymentInfo = $res->fetch_assoc();



        // Soft delete from trip_payments

        $delStmt = $conn->prepare("UPDATE trip_payments SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id = ?");

        $delStmt->bind_param("ii", $uncleId, $paymentId);

        if (!$delStmt->execute()) {

            sendJSON(['success' => false, 'message' => 'فشل في حذف الدفعة']);

            return;

        }



        // Update payment_history JSON in trip_registrations

        $historyStmt = $conn->prepare("SELECT payment_history FROM trip_registrations WHERE id = ?");

        $historyStmt->bind_param("i", $registrationId);

        $historyStmt->execute();

        $historyData = $historyStmt->get_result()->fetch_assoc();

        $historyArray = json_decode($historyData['payment_history'] ?? '[]', true) ?: [];



        $newHistory = [];

        foreach ($historyArray as $entry) {

            if (isset($entry['id']) && $entry['id'] == $paymentId)

                continue;

            $newHistory[] = $entry;

        }



        $updatedHistoryJson = json_encode($newHistory, JSON_UNESCAPED_UNICODE);

        $updateHistoryStmt = $conn->prepare("UPDATE trip_registrations SET payment_history = ? WHERE id = ?");

        $updateHistoryStmt->bind_param("si", $updatedHistoryJson, $registrationId);

        $updateHistoryStmt->execute();



        // Recalculate payment_status

        $finalPrice = $paymentInfo['price'];

        if ($paymentInfo['discount'] > 0) {

            if ($paymentInfo['discount_type'] === 'percentage') {

                $finalPrice = $paymentInfo['price'] - ($paymentInfo['price'] * $paymentInfo['discount'] / 100);

            } else {

                $finalPrice = max(0, $paymentInfo['price'] - $paymentInfo['discount']);

            }

        }



        $recalcStmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid FROM trip_payments WHERE registration_id = ? AND is_deleted = 0");

        $recalcStmt->bind_param("i", $registrationId);

        $recalcStmt->execute();

        $totalPaidNow = floatval($recalcStmt->get_result()->fetch_assoc()['total_paid']);

        $remainingNow = $finalPrice - $totalPaidNow;



        if (abs($remainingNow) < 0.01) {

            $newPaymentStatus = 'paid';

        } elseif ($totalPaidNow > 0.01) {

            $newPaymentStatus = 'partial';

        } else {

            $newPaymentStatus = 'pending';

        }



        $syncStmt = $conn->prepare("UPDATE trip_registrations SET payment_status = ? WHERE id = ?");

        $syncStmt->bind_param("si", $newPaymentStatus, $registrationId);

        $syncStmt->execute();



        sendJSON([

            'success' => true,

            'message' => 'تم حذف الدفعة بنجاح',

            'new_status' => $newPaymentStatus,

            'total_paid' => $totalPaidNow,

            'remaining' => max(0, $remainingNow)

        ]);



    } catch (Exception $e) {

        error_log("deleteTripPayment error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في حذف الدفعة: ' . $e->getMessage()]);

    }

}



function restoreTripPayment()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $paymentId = intval($_POST['payment_id'] ?? 0);

        $registrationId = intval($_POST['registration_id'] ?? 0);

        $uncleId = $_SESSION['uncle_id'] ?? null;



        if (!$paymentId || !$registrationId) {

            sendJSON(['success' => false, 'message' => 'بيانات غير مكتملة']);

            return;

        }



        $conn = getDBConnection();



        if (!verifyRegistrationParticipant($conn, $registrationId, $churchId)) {

            sendJSON(['success' => false, 'message' => 'الدفعة غير موجودة أو غير مصرح باستعادتها']);

            return;

        }



        // Verify payment belongs to this registration

        $checkStmt = $conn->prepare("

            SELECT tp.*, tr.trip_id, t.price, t.discount, t.discount_type

            FROM trip_payments tp

            JOIN trip_registrations tr ON tp.registration_id = tr.id

            JOIN trips t ON tr.trip_id = t.id

            WHERE tp.id = ? AND tr.id = ?

        ");

        $checkStmt->bind_param("ii", $paymentId, $registrationId);

        $checkStmt->execute();

        $res = $checkStmt->get_result();

        if ($res->num_rows === 0) {

            sendJSON(['success' => false, 'message' => 'الدفعة غير موجودة']);

            return;

        }

        $paymentInfo = $res->fetch_assoc();



        // Restore in trip_payments

        $restoreStmt = $conn->prepare("UPDATE trip_payments SET is_deleted = 0, deleted_at = NULL, deleted_by = NULL WHERE id = ?");

        $restoreStmt->bind_param("i", $paymentId);

        if (!$restoreStmt->execute()) {

            sendJSON(['success' => false, 'message' => 'فشل في استعادة الدفعة']);

            return;

        }



        // Audit log

        if (function_exists('logAudit')) {

            logAudit('trip_payment_restored', 'trip_payments', $paymentId, "Restored payment of {$paymentInfo['amount']} for registration {$registrationId}");

        }



        // Recalculate trip_registrations.payment_status

        $finalPrice = $paymentInfo['price'];

        if ($paymentInfo['discount'] > 0) {

            if ($paymentInfo['discount_type'] === 'percentage') {

                $finalPrice = $paymentInfo['price'] - ($paymentInfo['price'] * $paymentInfo['discount'] / 100);

            } else {

                $finalPrice = max(0, $paymentInfo['price'] - $paymentInfo['discount']);

            }

        }



        $recalcStmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid FROM trip_payments WHERE registration_id = ? AND is_deleted = 0");

        $recalcStmt->bind_param("i", $registrationId);

        $recalcStmt->execute();

        $totalPaidNow = floatval($recalcStmt->get_result()->fetch_assoc()['total_paid']);



        $remainingNow = $finalPrice - $totalPaidNow;



        if (abs($remainingNow) < 0.01) {

            $newPaymentStatus = 'paid';

        } elseif ($totalPaidNow > 0.01) {

            $newPaymentStatus = 'partial';

        } else {

            $newPaymentStatus = 'pending';

        }



        $syncStmt = $conn->prepare("UPDATE trip_registrations SET payment_status = ? WHERE id = ?");

        $syncStmt->bind_param("si", $newPaymentStatus, $registrationId);

        $syncStmt->execute();



        sendJSON([

            'success' => true,

            'message' => 'تم استعادة الدفعة بنجاح',

            'new_status' => $newPaymentStatus,

            'total_paid' => $totalPaidNow,

            'remaining' => max(0, $remainingNow)

        ]);



    } catch (Exception $e) {

        error_log("restoreTripPayment error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في استعادة الدفعة: ' . $e->getMessage()]);

    }

}



function exportTripData()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $tripId = intval($_POST['trip_id'] ?? $_GET['trip_id'] ?? 0);

        $format = sanitize($_POST['format'] ?? $_GET['format'] ?? 'csv');



        if ($tripId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف الرحلة مطلوب']);

            return;

        }



        $conn = getDBConnection();



        if (!verifyTripParticipant($conn, $tripId, $churchId)) {

            sendJSON(['success' => false, 'message' => 'الرحلة غير موجودة أو غير مصرح لك بتصدير بياناتها']);

            return;

        }



        // معلومات الرحلة

        $tripStmt = $conn->prepare("

            SELECT t.*, u.name as created_by_name

            FROM trips t

            LEFT JOIN uncles u ON t.created_by = u.id

            WHERE t.id = ?

        ");

        $tripStmt->bind_param("i", $tripId);

        $tripStmt->execute();

        $tripResult = $tripStmt->get_result();



        if ($tripResult->num_rows === 0) {

            sendJSON(['success' => false, 'message' => 'الرحلة غير موجودة']);

            return;

        }



        $trip = $tripResult->fetch_assoc();



        // حساب السعر بعد الخصم

        $finalPrice = $trip['price'];

        if ($trip['discount'] > 0) {

            if ($trip['discount_type'] === 'percentage') {

                $finalPrice = $trip['price'] - ($trip['price'] * $trip['discount'] / 100);

            } else {

                $finalPrice = max(0, $trip['price'] - $trip['discount']);

            }

        }



        // بيانات المسجلين مع إجمالي الدفعات والتبرعات (مجمعة لكل طفل)

        $regStmt = $conn->prepare("

            SELECT

                s.name as student_name,

                s.class as student_class,

                s.phone as student_phone,

                s.emergency_phone,

                s.medical_notes,

                s.gender as student_gender,

                tr.id as reg_id,

                tr.registration_date,

                tr.notes,

                tr.custom_data,

                COALESCE((SELECT SUM(amount) FROM trip_payments WHERE registration_id = tr.id AND is_deleted = 0), 0) as total_paid,

                COALESCE((SELECT SUM(donation) FROM trip_payments WHERE registration_id = tr.id AND is_deleted = 0), 0) as total_donation,

                u.name as registered_by_name,

                c.church_name as student_church

            FROM trip_registrations tr

            JOIN students s ON tr.student_id = s.id

            LEFT JOIN uncles u ON tr.registered_by = u.id

            LEFT JOIN churches c ON s.church_id = c.id

            WHERE tr.trip_id = ? AND tr.cancelled = 0

            ORDER BY tr.registration_date

        ");

        $regStmt->bind_param("i", $tripId);

        $regStmt->execute();

        $regResult = $regStmt->get_result();



        $registrations = [];

        while ($row = $regResult->fetch_assoc()) {

            $row['total_paid'] = floatval($row['total_paid']);

            $row['total_donation'] = floatval($row['total_donation']);

            $row['remaining'] = max(0, $finalPrice - $row['total_paid']);

            $row['payment_status'] = $row['remaining'] == 0

                ? 'مدفوع بالكامل'

                : ($row['total_paid'] > 0 ? 'مدفوع جزئياً' : 'غير مدفوع');

            $registrations[] = $row;

        }



        // سجل الدفعات التفصيلي (كل دفعة على حدة — بدون مكررات)

        $payStmt = $conn->prepare("

            SELECT

                s.name as student_name,

                tp.amount,

                tp.donation,

                tp.payment_date,

                tp.payment_method,

                tp.notes,

                u.name as received_by_name

            FROM trip_payments tp

            JOIN trip_registrations tr ON tp.registration_id = tr.id

            JOIN students s ON tr.student_id = s.id

            LEFT JOIN uncles u ON tp.received_by = u.id

            WHERE tr.trip_id = ? AND tr.cancelled = 0 AND tp.is_deleted = 0

            ORDER BY tp.payment_date

        ");

        $payStmt->bind_param("i", $tripId);

        $payStmt->execute();

        $payResult = $payStmt->get_result();



        $payments = [];

        while ($row = $payResult->fetch_assoc()) {

            $payments[] = $row;

        }



        // قائمة الانتظار (enriched totals + payment receivers for CSV)

        $waitlist = [];

        try {

            ensureWaitlistTable($conn);

            $waitlist = getWaitlistData($tripId, $conn);

            foreach ($waitlist as &$wRow) {

                $wRow['received_by_names'] = resolveWaitlistReceiversForCsv($conn, $wRow);

                $statusMap = [

                    'paid' => 'مدفوع بالكامل',

                    'partial' => 'مدفوع جزئياً',

                    'pending' => 'غير مدفوع',

                ];

                $wRow['payment_status_label'] = $statusMap[$wRow['payment_status'] ?? ''] ?? ($wRow['payment_status'] ?? '');

            }

            unset($wRow);

        } catch (Exception $we) { /* ignore if waitlist table missing */

        }



        if ($format === 'csv') {

            exportTripToCSV($trip, $finalPrice, $registrations, $payments, $waitlist);

        } else {

            exportTripToPDF($trip, $finalPrice, $registrations, $payments);

        }



    } catch (Exception $e) {

        error_log("exportTripData error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تصدير البيانات: ' . $e->getMessage()]);

    }

}





function csvNumericAmount($value)

{

    return round(floatval($value), 2);

}



function resolveWaitlistReceiversForCsv($conn, array $waitlistRow)

{

    $history = parseWaitlistPaymentHistory($waitlistRow['payment_history'] ?? null);

    $uncleIds = [];

    $inlineNames = [];



    foreach ($history as $p) {

        if (intval($p['is_deleted'] ?? 0) === 1) {

            continue;

        }

        $amount = floatval($p['amount'] ?? 0);

        $donation = floatval($p['donation'] ?? 0);

        if ($amount <= 0 && $donation <= 0) {

            continue;

        }

        $receiver = $p['received_by'] ?? null;

        if ($receiver === null || $receiver === '' || $receiver === '—') {

            continue;

        }

        if (is_numeric($receiver)) {

            $uncleIds[intval($receiver)] = true;

        } else {

            $inlineNames[] = trim((string) $receiver);

        }

    }



    if (empty($uncleIds) && empty($inlineNames)) {

        $fallbackId = intval($waitlistRow['added_by'] ?? 0);

        if ($fallbackId > 0 && floatval($waitlistRow['deposit'] ?? $waitlistRow['total_paid'] ?? 0) > 0) {

            $uncleIds[$fallbackId] = true;

        }

    }



    $names = array_values(array_unique(array_filter($inlineNames)));



    if (!empty($uncleIds)) {

        $ids = array_keys($uncleIds);

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $types = str_repeat('i', count($ids));

        $stmt = $conn->prepare("SELECT name FROM uncles WHERE id IN ($placeholders) ORDER BY name ASC");

        if ($stmt) {

            $stmt->bind_param($types, ...$ids);

            $stmt->execute();

            $result = $stmt->get_result();

            while ($u = $result->fetch_assoc()) {

                if (!empty($u['name'])) {

                    $names[] = $u['name'];

                }

            }

        }

    }



    $names = array_values(array_unique(array_filter($names)));

    return !empty($names) ? implode('، ', $names) : '';

}



function exportTripToCSV($trip, $finalPrice, $registrations, $payments = [], $waitlist = [])

{

    $filename = 'رحلة_' . str_replace(' ', '_', $trip['title']) . '_' . date('Y-m-d') . '.csv';



    header('Content-Type: text/csv; charset=utf-8');

    header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));



    $output = fopen('php://output', 'w');

    fwrite($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM



    // ─── معلومات الرحلة ───────────────────────────────────────────────────────

    fputcsv($output, ['معلومات الرحلة']);

    fputcsv($output, ['العنوان', $trip['title']]);

    fputcsv($output, ['الوصف', $trip['description'] ?? '']);

    fputcsv($output, ['النوع', $trip['type'] === 'one_day' ? 'رحلة (يوم واحد)' : 'مؤتمر (عدة أيام)']);

    fputcsv($output, ['تاريخ البدء', date('d/m/Y', strtotime($trip['start_date']))]);

    if (!empty($trip['end_date'])) {

        fputcsv($output, ['تاريخ الانتهاء', date('d/m/Y', strtotime($trip['end_date']))]);

    }

    fputcsv($output, ['السعر الأصلي', $trip['price'] . ' جنيه']);

    if ($trip['discount'] > 0) {

        $discountText = $trip['discount_type'] === 'percentage' ? $trip['discount'] . '%' : $trip['discount'] . ' جنيه';

        fputcsv($output, ['الخصم', $discountText]);

        fputcsv($output, ['السعر النهائي', $finalPrice . ' جنيه']);

    }

    fputcsv($output, ['الحد الأقصى', $trip['max_participants'] ?: 'غير محدد']);

    fputcsv($output, ['الحالة', $trip['status']]);

    fputcsv($output, ['']);



    // ─── بناء أعمدة البيانات الإضافية ─────────────────────────────────────────

    $customColumns = [];

    $fieldIconsMeta = [];

    if (!empty($trip['custom_field_icons'])) {

        $decoded = is_array($trip['custom_field_icons'])

            ? $trip['custom_field_icons']

            : json_decode($trip['custom_field_icons'], true);

        if (is_array($decoded)) {

            $fieldIconsMeta = $decoded;

        }

    }

    $plainFields = [];

    if (!empty($trip['custom_fields'])) {

        $plainFields = array_filter(array_map('trim', explode(',', $trip['custom_fields'])), fn($f) => $f !== '');

    }

    $processedFields = !empty($fieldIconsMeta) ? array_keys($fieldIconsMeta) : $plainFields;

    foreach ($processedFields as $fieldName) {

        $customColumns[] = ['key' => $fieldName, 'label' => $fieldName];

        $meta = $fieldIconsMeta[$fieldName] ?? [];

        if (!empty($meta['sub_fields']) && is_array($meta['sub_fields'])) {

            foreach ($meta['sub_fields'] as $choiceVal => $subObj) {

                if (!is_array($subObj))

                    continue;

                foreach ($subObj as $subName => $subMeta) {

                    $storageKey = $fieldName . '__sub__' . $choiceVal . '__' . $subName;

                    $label = $fieldName . ' (' . $choiceVal . ') → ' . $subName;

                    $customColumns[] = ['key' => $storageKey, 'label' => $label];

                }

            }

        }

    }



    // ─── قسم المسجلون ─────────────────────────────────────────────────────────

    fputcsv($output, ['قائمة المسجلين (' . count($registrations) . ')']);

    $headerRow = [

        '#',

        'اسم الطفل',

        'النوع',

        'الكنيسة',

        'الفصل',

        'رقم الهاتف',

        'هاتف الطوارئ',

        'ملاحظات طبية',

        'تاريخ التسجيل',

        'المدفوع',

        'التبرع',

        'المتبقي',

        'الحالة',

        'ملاحظات'

    ];

    foreach ($customColumns as $col) {

        $headerRow[] = $col['label'];

    }

    fputcsv($output, $headerRow);



    foreach ($registrations as $index => $reg) {

        $customData = [];

        if (!empty($reg['custom_data'])) {

            $parsed = json_decode($reg['custom_data'], true);

            if (is_array($parsed))

                $customData = $parsed;

        }

        $genderText = formatStudentGenderLabel($reg['student_gender'] ?? 'male');

        $row = [

            $index + 1,

            $reg['student_name'],

            $genderText,

            $reg['student_church'] ?? '',

            $reg['student_class'],

            $reg['student_phone'] ?? '',

            $reg['emergency_phone'] ?? '',

            $reg['medical_notes'] ?? '',

            date('d/m/Y H:i', strtotime($reg['registration_date'])),

            csvNumericAmount($reg['total_paid']),

            csvNumericAmount($reg['total_donation'] ?? 0),

            csvNumericAmount($reg['remaining']),

            $reg['payment_status'],

            $reg['notes'] ?? ''

        ];

        foreach ($customColumns as $col) {

            $row[] = $customData[$col['key']] ?? '';

        }

        fputcsv($output, $row);

    }



    // ─── إحصائيات الملخص ─────────────────────────────────────────────────────

    $totalPaid = array_sum(array_column($registrations, 'total_paid'));

    $totalDonation = array_sum(array_column($registrations, 'total_donation'));

    $totalRemaining = array_sum(array_column($registrations, 'remaining'));

    $totalExpected = $totalPaid + $totalRemaining;

    $paidCount = count(array_filter($registrations, fn($r) => $r['payment_status'] === 'مدفوع بالكامل'));

    $partialCount = count(array_filter($registrations, fn($r) => $r['payment_status'] === 'مدفوع جزئياً'));

    $pendingCount = count(array_filter($registrations, fn($r) => $r['payment_status'] === 'غير مدفوع'));



    fputcsv($output, ['']);

    fputcsv($output, ['الإحصائيات']);

    fputcsv($output, ['إجمالي المسجلين', count($registrations)]);

    fputcsv($output, ['مدفوع بالكامل', $paidCount]);

    fputcsv($output, ['مدفوع جزئياً', $partialCount]);

    fputcsv($output, ['غير مدفوع', $pendingCount]);

    fputcsv($output, ['إجمالي المحصّل', csvNumericAmount($totalPaid)]);

    fputcsv($output, ['إجمالي التبرعات', csvNumericAmount($totalDonation)]);

    fputcsv($output, ['إجمالي المتبقي', csvNumericAmount($totalRemaining)]);

    fputcsv($output, ['الإجمالي المتوقع', csvNumericAmount($totalExpected)]);



    // ─── سجل الدفعات التفصيلي ───────────────────────────────────────────────

    if (!empty($payments)) {

        fputcsv($output, ['']);

        fputcsv($output, ['سجل الدفعات التفصيلي (' . count($payments) . ' دفعة)']);

        fputcsv($output, ['#', 'اسم الطفل', 'المبلغ', 'التبرع', 'طريقة الدفع', 'المستلم', 'تاريخ الدفع', 'ملاحظات']);

        foreach ($payments as $index => $pay) {

            $methodAr = ['cash' => 'نقداً', 'card' => 'بطاقة', 'bank_transfer' => 'تحويل بنكي', 'other' => 'أخرى'][$pay['payment_method']] ?? $pay['payment_method'];

            fputcsv($output, [

                $index + 1,

                $pay['student_name'],

                csvNumericAmount($pay['amount']),

                csvNumericAmount($pay['donation']),

                $methodAr,

                $pay['received_by_name'] ?? '',

                date('d/m/Y H:i', strtotime($pay['payment_date'])),

                $pay['notes'] ?? ''

            ]);

        }

    }



    // ─── قائمة الانتظار ──────────────────────────────────────────────────────

    if (!empty($waitlist)) {

        fputcsv($output, ['']);

        fputcsv($output, ['قائمة الانتظار (' . count($waitlist) . ')']);

        fputcsv($output, ['الترتيب', 'اسم الطفل', 'الفصل', 'رقم الهاتف', 'المدفوع', 'التبرع', 'المتبقي', 'الحالة', 'تاريخ الإضافة', 'المستلم', 'ملاحظات']);

        foreach ($waitlist as $w) {

            fputcsv($output, [

                $w['position'],

                $w['student_name'],

                $w['student_class'] ?? '',

                $w['student_phone'] ?? '',

                csvNumericAmount($w['total_paid'] ?? $w['deposit'] ?? 0),

                csvNumericAmount($w['total_donation'] ?? $w['donation'] ?? 0),

                csvNumericAmount($w['remaining'] ?? 0),

                $w['payment_status_label'] ?? '',

                !empty($w['added_at']) ? date('d/m/Y H:i', strtotime($w['added_at'])) : '',

                $w['received_by_names'] ?? '',

                $w['notes'] ?? ''

            ]);

        }

    }



    fclose($output);

    exit;

}





function exportTripToPDF($trip, $finalPrice, $registrations)

{

    sendJSON(['success' => false, 'message' => 'تصدير PDF قيد التطوير، استخدم CSV حالياً']);

}





function getFridaysInMonth()

{

    try {

        $month = intval($_POST['month'] ?? $_GET['month'] ?? date('m'));

        $year = intval($_POST['year'] ?? $_GET['year'] ?? date('Y'));

        $targetDayDb = intval($_POST['attendance_day'] ?? 5); // DB: 1=Mon…7=Sun



        // Convert DB day to PHP date('N') format: 1=Mon…7=Sun (same as DB)

        $phpDayN = ($targetDayDb >= 1 && $targetDayDb <= 7) ? $targetDayDb : 5;



        $days = [];

        $date = new DateTime("$year-$month-01");

        $lastDay = clone $date;

        $lastDay->modify('last day of this month');



        while ($date <= $lastDay) {

            if ((int) $date->format('N') === $phpDayN) {

                $days[] = $date->format('Y-m-d');

            }

            $date->modify('+1 day');

        }



        sendJSON([

            'success' => true,

            'fridays' => $days,    // key kept as 'fridays' for BC

            'days' => $days,

            'month' => $month,

            'year' => $year,

            'attendance_day' => $phpDayN,

        ]);



    } catch (Exception $e) {

        error_log("getFridaysInMonth error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في جلب الأيام']);

    }

}





function getAttendanceByDateAndClass()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $date = sanitize($_POST['date'] ?? $_GET['date'] ?? date('Y-m-d'));

        $classId = intval($_POST['class_id'] ?? $_GET['class_id'] ?? 0);

        $classFilter = sanitize($_POST['class'] ?? $_GET['class'] ?? '');



        $conn = getDBConnection();



        $sql = "SELECT 

                    a.id, a.student_id, a.attendance_date, a.status,

                    s.name as student_name,

                    COALESCE(cc.arabic_name, c.arabic_name) as class,

                    s.class_id,

                    u.name as recorded_by

                FROM attendance a

                JOIN students s ON a.student_id = s.id

                LEFT JOIN classes c ON s.class_id = c.id

                LEFT JOIN church_classes cc ON s.class_id = cc.id AND cc.church_id = s.church_id

                LEFT JOIN uncles u ON a.uncle_id = u.id

                WHERE a.church_id = ? AND a.attendance_date = ?";



        $params = [$churchId, $date];

        $types = "is";



        if ($classId > 0) {

            $sql .= " AND s.class_id = ?";

            $params[] = $classId;

            $types .= "i";

        } elseif (!empty($classFilter)) {

            $sql .= " AND (c.arabic_name = ? OR cc.arabic_name = ?)";

            $params[] = $classFilter;

            $params[] = $classFilter;

            $types .= "ss";

        }



        $sql .= " ORDER BY s.name";



        $stmt = $conn->prepare($sql);

        $stmt->bind_param($types, ...$params);

        $stmt->execute();

        $result = $stmt->get_result();



        $attendance = [];

        while ($row = $result->fetch_assoc()) {

            $attendance[] = [

                'id' => $row['id'],

                'student_id' => $row['student_id'],

                'student_name' => $row['student_name'],

                'class' => $row['class'] ?? 'بدون فصل',

                'class_id' => $row['class_id'],

                'date' => date('d/m/Y', strtotime($row['attendance_date'])),

                'status' => $row['status'],

                'status_text' => $row['status'] === 'present' ? 'حاضر' : 'غائب',

                'recorded_by' => $row['recorded_by'] ?? '---'

            ];

        }



        sendJSON([

            'success' => true,

            'attendance' => $attendance,

            'count' => count($attendance),

            'date' => $date

        ]);



    } catch (Exception $e) {

        error_log("getAttendanceByDateAndClass error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في جلب الحضور']);

    }

}





function updateSingleAttendance()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $uncleId = $_SESSION['uncle_id'] ?? null;



        $attendanceId = intval($_POST['attendance_id'] ?? 0);

        $status = sanitize($_POST['status'] ?? '');



        if ($attendanceId === 0 || !in_array($status, ['present', 'absent'])) {

            sendJSON(['success' => false, 'message' => 'بيانات غير صحيحة']);

            return;

        }



        $conn = getDBConnection();



        // BEFORE the UPDATE, get the old record

        $oldAtt = getAttendanceSnapshot($attendanceId);



        $stmt = $conn->prepare("

            UPDATE attendance 

            SET status = ?, uncle_id = ?, updated_at = NOW()

            WHERE id = ? AND church_id = ?

        ");

        $stmt->bind_param("siii", $status, $uncleId, $attendanceId, $churchId);



        if ($stmt->execute() && $stmt->affected_rows > 0) {

            // ► AUDIT

            writeAuditLog(

                'attendance_edit',

                'attendance',

                $attendanceId,

                $oldAtt['student_name'] ?? '',

                $oldAtt,

                array_merge($oldAtt ?? [], ['status' => $status]),

                "تعديل حضور يدوي"

            );



            sendJSON(['success' => true, 'message' => 'تم تحديث الحضور بنجاح']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في تحديث الحضور']);

        }



    } catch (Exception $e) {

        error_log("updateSingleAttendance error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحديث الحضور']);

    }

}



function saveClassSettings()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $settings = json_decode($_POST['settings'] ?? '[]', true);



        $conn = getDBConnection();



        // تحديث جدول church_classes حسب الإعدادات

        $conn->begin_transaction();



        // مسح الفصول الحالية — نستخدم is_active=0 بدلاً من DELETE لحماية بيانات الأطفال

        $deactStmt = $conn->prepare("UPDATE church_classes SET is_active = 0 WHERE church_id = ?");

        $deactStmt->bind_param("i", $churchId);

        $deactStmt->execute();



        if (!empty($settings)) {

            $insertStmt = $conn->prepare("

                INSERT INTO church_classes (church_id, code, arabic_name, display_order, color, is_active)

                VALUES (?, ?, ?, ?, ?, 1)

                ON DUPLICATE KEY UPDATE

                    arabic_name   = VALUES(arabic_name),

                    display_order = VALUES(display_order),

                    color         = VALUES(color),

                    is_active     = 1

            ");



            foreach ($settings as $class) {

                $code = sanitize($class['code'] ?? '');

                $name = sanitize($class['arabic_name'] ?? '');

                $order = intval($class['display_order'] ?? 0);

                $color = sanitize($class['color'] ?? '#4f46e5');



                if (empty($code) || empty($name))

                    continue;



                $insertStmt->bind_param("issis", $churchId, $code, $name, $order, $color);

                $insertStmt->execute();

            }

        }



        $conn->commit();



        sendJSON(['success' => true, 'message' => 'تم حفظ إعدادات الفصول بنجاح']);



    } catch (Exception $e) {

        if (isset($conn))

            $conn->rollback();

        error_log("saveClassSettings error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في حفظ الإعدادات: ' . $e->getMessage()]);

    }

}



function updateStudentFull()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $studentId = intval($_POST['student_id'] ?? 0);



        if ($studentId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف الطفل مطلوب']);

            return;

        }



        $name = sanitize($_POST['name'] ?? '');

        $classId = intval($_POST['class_id'] ?? 0);

        $address = sanitize($_POST['address'] ?? '');

        $phone = sanitize($_POST['phone'] ?? '');

        $emergencyPhone = sanitize($_POST['emergency_phone'] ?? '');

        $medicalNotes = sanitize($_POST['medical_notes'] ?? '');

        $birthday = sanitize($_POST['birthday'] ?? '');



        if (empty($name) || $classId === 0) {

            sendJSON(['success' => false, 'message' => 'الاسم والفصل مطلوبان']);

            return;

        }



        $dbBirthday = !empty($birthday) ? formatDateToDB($birthday) : null;



        $conn = getDBConnection();



        // الحصول على اسم الفصل

        $classStmt = $conn->prepare("

            SELECT COALESCE(cc.arabic_name, c.arabic_name) as class_name

            FROM church_classes cc

            LEFT JOIN classes c ON cc.id = c.id

            WHERE cc.id = ? AND cc.church_id = ?

            UNION

            SELECT c.arabic_name

            FROM classes c

            WHERE c.id = ? AND NOT EXISTS (

                SELECT 1 FROM church_classes WHERE id = ? AND church_id = ?

            )

        ");

        $classStmt->bind_param("iiiii", $classId, $churchId, $classId, $classId, $churchId);

        $classStmt->execute();

        $classResult = $classStmt->get_result();

        $classData = $classResult->fetch_assoc();

        $className = $classData['class_name'] ?? '';



        // معالجة رفع الصورة

        $imageUrl = null;

        if (isset($_FILES['student_image']) && $_FILES['student_image']['error'] === UPLOAD_ERR_OK) {

            $uploadDir = __DIR__ . '/uploads/students/';



            if (!is_dir($uploadDir)) {

                mkdir($uploadDir, 0755, true);

            }



            $filename = 'student_' . $studentId . '_' . time() . '.jpg';

            $uploadPath = $uploadDir . $filename;



            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];

            $fileType = mime_content_type($_FILES['student_image']['tmp_name']);



            if (in_array($fileType, $allowedTypes)) {

                if (move_uploaded_file($_FILES['student_image']['tmp_name'], $uploadPath)) {

                    $imageUrl = "https://sunday-school.online/uploads/students/" . $filename;

                }

            }

        }



        ensureStudentSiblingGroupTables($conn);



        $existingCustomStmt = $conn->prepare("SELECT custom_info FROM students WHERE id = ? AND church_id = ? LIMIT 1");

        $existingCustomStmt->bind_param('ii', $studentId, $churchId);

        $existingCustomStmt->execute();

        $existingCustomRow = $existingCustomStmt->get_result()->fetch_assoc();

        $existingCustomInfoRaw = $existingCustomRow['custom_info'] ?? null;



        $hasCustomInfo = array_key_exists('custom_info', $_POST);

        $customInfoRaw = $_POST['custom_info'] ?? null;

        $customInfoJson = null;

        if ($hasCustomInfo) {

            if (trim((string) $customInfoRaw) === '') {

                $customInfoJson = mergeStudentCustomInfoForUpdate($conn, $studentId, $existingCustomInfoRaw, []);

            } else {

                $decoded = json_decode($customInfoRaw, true);

                if (!is_array($decoded)) {

                    $decoded = ['field_0' => sanitize($customInfoRaw)];

                }

                $customInfoJson = mergeStudentCustomInfoForUpdate($conn, $studentId, $existingCustomInfoRaw, $decoded);

            }

        }



        $gender = sanitize($_POST['gender'] ?? '');

        if ($gender !== 'male' && $gender !== 'female') {

            $gender = detectGenderFromName($name);

        }



        // بناء استعلام التحديث

        if ($imageUrl) {

            $stmt = $conn->prepare("

                UPDATE students 

                SET name = ?, class_id = ?, class = ?, address = ?, 

                    phone = ?, emergency_phone = ?, medical_notes = ?, 

                    birthday = ?, image_url = ?, custom_info = ?, gender = ?, updated_at = NOW()

                WHERE id = ? AND church_id = ?

            ");

            safeBindParam(

                $stmt,

                $name,

                $classId,

                $className,

                $address,

                $phone,

                $emergencyPhone,

                $medicalNotes,

                $dbBirthday,

                $imageUrl,

                $customInfoJson,

                $gender,

                $studentId,

                $churchId

            );

        } else {

            $stmt = $conn->prepare("

                UPDATE students 

                SET name = ?, class_id = ?, class = ?, address = ?, 

                    phone = ?, emergency_phone = ?, medical_notes = ?, 

                    birthday = ?, custom_info = ?, gender = ?, updated_at = NOW()

                WHERE id = ? AND church_id = ?

            ");

            safeBindParam(

                $stmt,

                $name,

                $classId,

                $className,

                $address,

                $phone,

                $emergencyPhone,

                $medicalNotes,

                $dbBirthday,

                $customInfoJson,

                $gender,

                $studentId,

                $churchId

            );

        }



        if ($stmt->execute()) {

            sendJSON([

                'success' => true,

                'message' => 'تم تحديث معلومات الطفل بنجاح',

                'image_url' => $imageUrl

            ]);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في تحديث المعلومات: ' . $stmt->error]);

        }



    } catch (Exception $e) {

        error_log("updateStudentFull error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحديث المعلومات: ' . $e->getMessage()]);

    }

}



// ── Sibling groups (persisted by student id) ─────────────────

function ensureStudentSiblingGroupTables($conn)

{

    $conn->query("CREATE TABLE IF NOT EXISTS `student_sibling_groups` (

        `id` varchar(64) NOT NULL,

        `church_id` int(11) NOT NULL,

        `label` varchar(255) DEFAULT '',

        `status` enum('approved','pending','rejected') NOT NULL DEFAULT 'approved',

        `linked_by_id` int(11) DEFAULT NULL,

        `linked_by_name` varchar(255) DEFAULT '',

        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),

        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),

        PRIMARY KEY (`id`),

        KEY `church_id` (`church_id`)

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");



    $conn->query("CREATE TABLE IF NOT EXISTS `student_sibling_group_members` (

        `student_id` int(11) NOT NULL,

        `group_id` varchar(64) NOT NULL,

        `church_id` int(11) NOT NULL,

        `added_at` timestamp NOT NULL DEFAULT current_timestamp(),

        PRIMARY KEY (`student_id`),

        KEY `group_id` (`group_id`),

        KEY `church_id` (`church_id`)

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");



    migrateSiblingGroupsFromCustomInfo($conn);

}



function migrateSiblingGroupsFromCustomInfo($conn)

{

    $res = $conn->query("SELECT id, church_id, custom_info FROM students WHERE custom_info IS NOT NULL AND custom_info LIKE '%sibling_group%'");

    if (!$res) {

        return;

    }



    $groupStmt = $conn->prepare("INSERT INTO student_sibling_groups (id, church_id, label, status, linked_by_id, linked_by_name)

        VALUES (?, ?, ?, ?, ?, ?)

        ON DUPLICATE KEY UPDATE

            label = IF(VALUES(label) <> '', VALUES(label), label),

            status = VALUES(status),

            linked_by_id = COALESCE(VALUES(linked_by_id), linked_by_id),

            linked_by_name = IF(VALUES(linked_by_name) <> '', VALUES(linked_by_name), linked_by_name)");

    $memberStmt = $conn->prepare("INSERT IGNORE INTO student_sibling_group_members (student_id, group_id, church_id) VALUES (?, ?, ?)");



    while ($row = $res->fetch_assoc()) {

        $custom = json_decode($row['custom_info'] ?? '', true);

        if (!is_array($custom) || empty($custom['sibling_group']['id'])) {

            continue;

        }

        $sg = $custom['sibling_group'];

        $groupId = sanitize($sg['id']);

        if ($groupId === '') {

            continue;

        }

        $churchId = intval($row['church_id']);

        $studentId = intval($row['id']);

        $label = sanitize($sg['label'] ?? '');

        $status = sanitize($sg['status'] ?? 'approved');

        if (!in_array($status, ['approved', 'pending', 'rejected'], true)) {

            $status = 'approved';

        }

        $linkedById = intval($sg['linked_by_id'] ?? 0);

        $linkedByName = sanitize($sg['linked_by'] ?? '');

        $groupStmt->bind_param('sissis', $groupId, $churchId, $label, $status, $linkedById, $linkedByName);

        $groupStmt->execute();

        $memberStmt->bind_param('isi', $studentId, $groupId, $churchId);

        $memberStmt->execute();

    }

}



function getStudentSiblingGroupMeta($conn, $studentId)

{

    $stmt = $conn->prepare("

        SELECT ssg.id, ssg.label, ssg.status, ssg.linked_by_id, ssg.linked_by_name,

               ssg.updated_at, ssgm.added_at

        FROM student_sibling_group_members ssgm

        INNER JOIN student_sibling_groups ssg ON ssg.id = ssgm.group_id

        WHERE ssgm.student_id = ?

        LIMIT 1

    ");

    if (!$stmt) {

        return null;

    }

    $stmt->bind_param('i', $studentId);

    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {

        return null;

    }

    return [

        'id' => $row['id'],

        'label' => $row['label'] ?? '',

        'status' => $row['status'] ?? 'approved',

        'linked_by_id' => intval($row['linked_by_id'] ?? 0) ?: null,

        'linked_by' => $row['linked_by_name'] ?? '',

        'linked_at' => !empty($row['added_at']) ? date('c', strtotime($row['added_at'])) : date('c'),

        'updated_at' => !empty($row['updated_at']) ? date('c', strtotime($row['updated_at'])) : date('c'),

    ];

}



function syncSiblingGroupToStudentCustomInfo($conn, $studentId)

{

    $chk = $conn->prepare("SELECT custom_info FROM students WHERE id = ? LIMIT 1");

    $chk->bind_param('i', $studentId);

    $chk->execute();

    $row = $chk->get_result()->fetch_assoc();

    if (!$row) {

        return;

    }



    $customInfo = [];

    if (!empty($row['custom_info'])) {

        $decoded = json_decode($row['custom_info'], true);

        if (is_array($decoded)) {

            $customInfo = $decoded;

        }

    }



    $meta = getStudentSiblingGroupMeta($conn, $studentId);

    if ($meta) {

        $customInfo['sibling_group'] = $meta;

    } else {

        unset($customInfo['sibling_group']);

    }



    $customJson = empty($customInfo) ? null : json_encode($customInfo, JSON_UNESCAPED_UNICODE);

    $up = $conn->prepare("UPDATE students SET custom_info = ? WHERE id = ?");

    $up->bind_param('si', $customJson, $studentId);

    $up->execute();

}



function mergeStudentCustomInfoForUpdate($conn, $studentId, $existingRaw, $incomingDecoded)

{

    $existing = [];

    if (!empty($existingRaw)) {

        $decodedExisting = json_decode($existingRaw, true);

        if (is_array($decodedExisting)) {

            $existing = $decodedExisting;

        }

    }



    $incoming = is_array($incomingDecoded) ? $incomingDecoded : [];



    $merged = $incoming;

    foreach ($existing as $key => $value) {

        if ($key === 'sibling_group') {

            continue;

        }

        if (!array_key_exists($key, $merged)) {

            $merged[$key] = $value;

        }

    }



    $siblingMeta = getStudentSiblingGroupMeta($conn, $studentId);

    if ($siblingMeta) {

        $merged['sibling_group'] = $siblingMeta;

    } else {

        unset($merged['sibling_group']);

    }



    return empty($merged) ? null : json_encode($merged, JSON_UNESCAPED_UNICODE);

}



function appendSiblingGroupToStudentPayload(&$studentData, $row)

{

    $groupId = $row['sibling_group_id'] ?? '';

    if ($groupId === '' || $groupId === null) {

        return;

    }

    $meta = [

        'id' => $groupId,

        'label' => $row['sibling_group_label'] ?? '',

        'status' => $row['sibling_group_status'] ?? 'approved',

        'linked_by_id' => intval($row['sibling_group_linked_by_id'] ?? 0) ?: null,

        'linked_by' => $row['sibling_group_linked_by_name'] ?? '',

        'linked_at' => !empty($row['sibling_member_added_at'])

            ? date('c', strtotime($row['sibling_member_added_at']))

            : date('c'),

        'updated_at' => !empty($row['sibling_group_updated_at'])

            ? date('c', strtotime($row['sibling_group_updated_at']))

            : date('c'),

    ];

    $studentData['_siblingGroupId'] = $groupId;

    $studentData['_siblingGroup'] = $meta;

    if (!is_array($studentData['_customInfo'])) {

        $studentData['_customInfo'] = [];

    }

    $studentData['_customInfo']['sibling_group'] = $meta;

}



function saveSiblingGroup()

{

    try {

        checkAuth();

        $studentIds = json_decode($_POST['student_ids'] ?? '[]', true);

        $status = sanitize($_POST['status'] ?? 'approved');

        if (!in_array($status, ['approved', 'rejected', 'pending'], true)) {

            $status = 'approved';

        }

        $label = sanitize($_POST['label'] ?? '');

        $groupId = sanitize($_POST['group_id'] ?? '');

        $op = sanitize($_POST['op'] ?? 'assign');

        $reason = sanitize($_POST['reason'] ?? '');

        $basis = sanitize($_POST['basis'] ?? '');

        $linkedBy = sanitize($_POST['linked_by'] ?? ($_SESSION['uncle_name'] ?? ''));

        $linkedById = intval($_SESSION['uncle_id'] ?? 0);

        if ($groupId === '') {

            $groupId = 'family_' . time() . '_' . substr(md5(uniqid('', true)), 0, 8);

        }



        if (!is_array($studentIds) || empty($studentIds)) {

            sendJSON(['success' => false, 'message' => 'معرفات الطلاب مطلوبة']);

            return;

        }



        $conn = getDBConnection();

        ensureStudentSiblingGroupTables($conn);

        $churchId = getChurchId();

        $errors = [];



        $validIds = array_values(array_unique(array_filter(array_map('intval', $studentIds), function ($id) {

            return $id > 0;

        })));



        if ($op === 'clear') {

            $delMember = $conn->prepare("DELETE FROM student_sibling_group_members WHERE student_id = ?");

            foreach ($validIds as $studentId) {

                $own = $conn->prepare("SELECT id FROM students WHERE id = ? AND church_id = ? LIMIT 1");

                $own->bind_param('ii', $studentId, $churchId);

                $own->execute();

                if ($own->get_result()->num_rows === 0) {

                    continue;

                }

                $delMember->bind_param('i', $studentId);

                if (!$delMember->execute()) {

                    $errors[] = "ID $studentId: " . $delMember->error;

                    continue;

                }

                syncSiblingGroupToStudentCustomInfo($conn, $studentId);

            }

        } else {

            if (empty($validIds)) {

                sendJSON(['success' => false, 'message' => 'معرفات الطلاب مطلوبة']);

                return;

            }



            $grpStmt = $conn->prepare("INSERT INTO student_sibling_groups (id, church_id, label, status, linked_by_id, linked_by_name)

                VALUES (?, ?, ?, ?, ?, ?)

                ON DUPLICATE KEY UPDATE

                    label = IF(VALUES(label) <> '', VALUES(label), label),

                    status = VALUES(status),

                    linked_by_id = COALESCE(VALUES(linked_by_id), linked_by_id),

                    linked_by_name = IF(VALUES(linked_by_name) <> '', VALUES(linked_by_name), linked_by_name),

                    updated_at = NOW()");

            $grpStmt->bind_param('sissis', $groupId, $churchId, $label, $status, $linkedById, $linkedBy);

            if (!$grpStmt->execute()) {

                sendJSON(['success' => false, 'message' => 'فشل حفظ مجموعة الإخوة: ' . $grpStmt->error]);

                return;

            }



            $ownStmt = $conn->prepare("SELECT id FROM students WHERE id = ? AND church_id = ? LIMIT 1");

            $delMember = $conn->prepare("DELETE FROM student_sibling_group_members WHERE student_id = ?");

            $insMember = $conn->prepare("INSERT INTO student_sibling_group_members (student_id, group_id, church_id) VALUES (?, ?, ?)");



            foreach ($validIds as $studentId) {

                $ownStmt->bind_param('ii', $studentId, $churchId);

                $ownStmt->execute();

                if ($ownStmt->get_result()->num_rows === 0) {

                    $errors[] = "ID $studentId: غير موجود في الكنيسة";

                    continue;

                }

                $delMember->bind_param('i', $studentId);

                if (!$delMember->execute()) {

                    $errors[] = "ID $studentId: " . $delMember->error;

                    continue;

                }

                $insMember->bind_param('isi', $studentId, $groupId, $churchId);

                if (!$insMember->execute()) {

                    $errors[] = "ID $studentId: " . $insMember->error;

                    continue;

                }

                syncSiblingGroupToStudentCustomInfo($conn, $studentId);

            }

        }



        if (!empty($errors)) {

            sendJSON(['success' => false, 'message' => 'بعض التحديثات فشلت: ' . implode('; ', $errors)]);

            return;

        }



        if ($op !== 'clear' && function_exists('logActivity')) {

            $details = "group:$groupId;student_ids:" . implode(',', array_map('intval', $studentIds)) . ";basis:" . ($basis ?: 'manual') . ";reason:" . ($reason ?: 'manual_link');

            logActivity(getChurchId(), $linkedById ?: null, 'sibling_group', $details);

        }



        sendJSON(['success' => true, 'message' => 'تم حفظ بيانات العائلة بنجاح']);

    } catch (Exception $e) {

        error_log("saveSiblingGroup error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في حفظ بيانات الأسرة']);

    }

}



function getClassSettings()

{

    try {

        checkAuth();

        $churchId = getChurchId();



        $conn = getDBConnection();



        // التحقق من وجود فصول مخصصة

        $customStmt = $conn->prepare("

            SELECT COUNT(*) as cnt FROM church_classes WHERE church_id = ?

        ");

        $customStmt->bind_param("i", $churchId);

        $customStmt->execute();

        $hasCustom = $customStmt->get_result()->fetch_assoc()['cnt'] > 0;



        if ($hasCustom) {

            $stmt = $conn->prepare("

                SELECT id, code, arabic_name, display_order, color

                FROM church_classes

                WHERE church_id = ? AND is_active = 1

                ORDER BY display_order

            ");

            $stmt->bind_param("i", $churchId);

        } else {

            $stmt = $conn->prepare("

                SELECT id, code, arabic_name, display_order, color

                FROM classes

                ORDER BY display_order

            ");

        }



        $stmt->execute();

        $result = $stmt->get_result();



        $classes = [];

        while ($row = $result->fetch_assoc()) {

            $classes[] = $row;

        }



        sendJSON([

            'success' => true,

            'classes' => $classes,

            'has_custom' => $hasCustom

        ]);



    } catch (Exception $e) {

        error_log("getClassSettings error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في جلب إعدادات الفصول']);

    }

}





function logActivity($churchId, $uncleId, $action, $details)

{

    try {

        $conn = getDBConnection();

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';



        $stmt = $conn->prepare("

            INSERT INTO activity_logs (church_id, uncle_id, action, details, ip_address, user_agent)

            VALUES (?, ?, ?, ?, ?, ?)

        ");

        $stmt->bind_param("iissss", $churchId, $uncleId, $action, $details, $ip, $userAgent);

        $stmt->execute();

    } catch (Exception $e) {

        // تجاهل الأخطاء في تسجيل النشاطات

        error_log("logActivity error: " . $e->getMessage());

    }

}

function getTripExpenses()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $tripId = intval($_POST['trip_id'] ?? $_GET['trip_id'] ?? 0);



        if ($tripId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف الرحلة مطلوب']);

            return;

        }



        $conn = getDBConnection();

        if (!verifyTripParticipant($conn, $tripId, $churchId)) {

            sendJSON(['success' => false, 'message' => 'غير مصرح لك بعرض مصروفات هذه الرحلة']);

            return;

        }



        // Ensure table exists

        $conn->query("CREATE TABLE IF NOT EXISTS `trip_expenses` (

            `id` INT AUTO_INCREMENT PRIMARY KEY,

            `trip_id` INT NOT NULL,

            `church_id` INT NOT NULL,

            `name` VARCHAR(200) DEFAULT NULL,

            `type` ENUM('bus','food','entry','other') NOT NULL DEFAULT 'other',

            `amount` DECIMAL(10,2) NOT NULL DEFAULT 0,

            `funding` ENUM('kids','funded','partial') NOT NULL DEFAULT 'kids',

            `per_kid` DECIMAL(10,2) NOT NULL DEFAULT 0,

            `notes` TEXT DEFAULT NULL,

            `created_by` INT DEFAULT NULL,

            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");



        $stmt = $conn->prepare("

            SELECT te.*, u.name as created_by_name

            FROM trip_expenses te

            LEFT JOIN uncles u ON te.created_by = u.id

            WHERE te.trip_id = ? AND te.church_id = ?

            ORDER BY te.created_at

        ");

        $stmt->bind_param("ii", $tripId, $churchId);

        $stmt->execute();

        $result = $stmt->get_result();



        $expenses = [];

        while ($row = $result->fetch_assoc()) {

            $expenses[] = $row;

        }



        sendJSON(['success' => true, 'expenses' => $expenses]);



    } catch (Exception $e) {

        error_log("getTripExpenses error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في جلب المصروفات']);

    }

}



// ── SAVE TRIP EXPENSE ─────────────────────────────────────────

function saveTripExpense()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $uncleId = $_SESSION['uncle_id'] ?? null;

        $tripId = intval($_POST['trip_id'] ?? 0);

        $name = sanitize($_POST['name'] ?? '');

        $type = sanitize($_POST['type'] ?? 'other');

        $amount = floatval($_POST['amount'] ?? 0);

        $funding = sanitize($_POST['funding'] ?? 'kids');

        $perKid = floatval($_POST['per_kid'] ?? 0);

        $notes = sanitize($_POST['notes'] ?? '');



        if ($tripId === 0 || $amount <= 0) {

            sendJSON(['success' => false, 'message' => 'بيانات غير صحيحة']);

            return;

        }



        // Verify trip belongs to this church or is collaborated

        $conn = getDBConnection();

        if (!verifyTripParticipant($conn, $tripId, $churchId)) {

            sendJSON(['success' => false, 'message' => 'الرحلة غير موجودة أو غير مصرح لك بإضافة مصروفات لها']);

            return;

        }



        // Ensure table exists

        $conn->query("CREATE TABLE IF NOT EXISTS `trip_expenses` (

            `id` INT AUTO_INCREMENT PRIMARY KEY,

            `trip_id` INT NOT NULL,

            `church_id` INT NOT NULL,

            `name` VARCHAR(200) DEFAULT NULL,

            `type` ENUM('bus','food','entry','other') NOT NULL DEFAULT 'other',

            `amount` DECIMAL(10,2) NOT NULL DEFAULT 0,

            `funding` ENUM('kids','funded','partial') NOT NULL DEFAULT 'kids',

            `per_kid` DECIMAL(10,2) NOT NULL DEFAULT 0,

            `notes` TEXT DEFAULT NULL,

            `created_by` INT DEFAULT NULL,

            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");



        $stmt = $conn->prepare("

            INSERT INTO trip_expenses (trip_id, church_id, name, type, amount, funding, per_kid, notes, created_by)

            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)

        ");

        $stmt->bind_param("iissdsdsi", $tripId, $churchId, $name, $type, $amount, $funding, $perKid, $notes, $uncleId);



        if ($stmt->execute()) {

            sendJSON(['success' => true, 'message' => 'تم إضافة المصروف', 'expense_id' => $conn->insert_id]);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في حفظ المصروف: ' . $stmt->error]);

        }



    } catch (Exception $e) {

        error_log("saveTripExpense error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في حفظ المصروف']);

    }

}



// ── DELETE TRIP EXPENSE ───────────────────────────────────────

function deleteTripExpense()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $expenseId = intval($_POST['expense_id'] ?? 0);

        $tripId = intval($_POST['trip_id'] ?? 0);



        if ($expenseId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف المصروف مطلوب']);

            return;

        }



        $conn = getDBConnection();



        // Verify ownership via church_id

        $stmt = $conn->prepare("DELETE FROM trip_expenses WHERE id = ? AND church_id = ?");

        $stmt->bind_param("ii", $expenseId, $churchId);



        if ($stmt->execute()) {

            sendJSON(['success' => true, 'message' => 'تم حذف المصروف']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في حذف المصروف']);

        }



    } catch (Exception $e) {

        error_log("deleteTripExpense error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في حذف المصروف']);

    }

}



// ── GET ATTENDANCE BY DATE (general – used by admin dashboard) ─

function getAttendanceByDate()

{

    try {

        checkAuth();

        $churchId = getChurchId();



        // Ensure date is in YYYY-MM-DD format

        $rawDate = $_POST['date'] ?? $_GET['date'] ?? '';

        $date = '';



        if (!empty($rawDate)) {

            // If date is in DD/MM/YYYY format, convert to YYYY-MM-DD

            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $rawDate, $matches)) {

                $date = $matches[3] . '-' . $matches[2] . '-' . $matches[1];

            }

            // If date is already in YYYY-MM-DD format

            elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {

                $date = $rawDate;

            }

            // If date is in other format, try to parse it

            else {

                try {

                    $dateObj = new DateTime($rawDate);

                    $date = $dateObj->format('Y-m-d');

                } catch (Exception $e) {

                    error_log("Invalid date format: $rawDate");

                }

            }

        }



        // If no valid date, use today

        if (empty($date)) {

            $date = date('Y-m-d');

        }



        $classFilter = sanitize($_POST['class'] ?? $_GET['class'] ?? '');



        $conn = getDBConnection();



        $sql = "

            SELECT

                a.id,

                a.student_id,

                a.attendance_date,

                a.status,

                s.name  AS student_name,

                s.image_url AS student_image,

                COALESCE(cc.arabic_name, c.arabic_name, s.class) AS class,

                u.name  AS recorded_by

            FROM attendance a

            JOIN students s ON a.student_id = s.id

            LEFT JOIN classes c          ON s.class_id = c.id

            LEFT JOIN church_classes cc  ON s.class_id = cc.id AND cc.church_id = s.church_id AND cc.is_active = 1

            LEFT JOIN uncles u           ON a.uncle_id  = u.id

            WHERE a.church_id = ?

              AND a.attendance_date = ?

        ";



        $params = [$churchId, $date];

        $types = "is";



        if (!empty($classFilter) && $classFilter !== 'جميع' && $classFilter !== 'الجميع') {

            $sql .= " AND (c.arabic_name = ? OR cc.arabic_name = ? OR s.class = ?)";

            $params[] = $classFilter;

            $params[] = $classFilter;

            $params[] = $classFilter;

            $types .= "sss";

        }



        $sql .= " ORDER BY s.name";



        $stmt = $conn->prepare($sql);

        $stmt->bind_param($types, ...$params);

        $stmt->execute();

        $result = $stmt->get_result();



        $attendance = [];

        while ($row = $result->fetch_assoc()) {

            // Format date for display

            $displayDate = !empty($row['attendance_date'])

                ? date('d/m/Y', strtotime($row['attendance_date']))

                : '';



            $attendance[] = [

                'id' => $row['id'],

                'student_id' => $row['student_id'],

                'student_name' => $row['student_name'],

                'student_image' => $row['student_image'] ?? '',

                'class' => $row['class'] ?? '---',

                'date' => $row['attendance_date'],

                'display_date' => $displayDate,

                'status' => $row['status'],

                'status_text' => $row['status'] === 'present' ? 'حاضر' : 'غائب',

                'recorded_by' => $row['recorded_by'] ?? '---',

            ];

        }



        sendJSON([

            'success' => true,

            'attendance' => $attendance,

            'count' => count($attendance),

            'date' => $date,

            'display_date' => date('d/m/Y', strtotime($date))

        ]);



    } catch (Exception $e) {

        error_log("getAttendanceByDate error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في جلب الحضور: ' . $e->getMessage()]);

    }

}

function getSessionInfo()

{

    runBackgroundGradeUpChecks();

    // Works for BOTH church logins and uncle logins

    $churchId = 0;

    $churchName = '';

    $churchCode = '';

    $churchType = 'kids'; // default

    $uncleId = null;

    $uncleName = '';

    $uncleRole = '';



    // Church direct login

    if (isset($_SESSION['church_id'])) {

        $churchId = intval($_SESSION['church_id']);

        $churchName = $_SESSION['church_name'] ?? '';

        $churchCode = $_SESSION['church_code'] ?? '';

        $churchType = $_SESSION['church_type'] ?? 'kids';

    }



    // Uncle login (also has church_id in session)

    if (isset($_SESSION['uncle_id'])) {

        $uncleId = intval($_SESSION['uncle_id']);

        $uncleName = $_SESSION['uncle_name'] ?? '';

        $uncleRole = $_SESSION['uncle_role'] ?? '';

        // If uncle session also has church_id, use it

        if (!$churchId && isset($_SESSION['church_id'])) {

            $churchId = intval($_SESSION['church_id']);

            $churchName = $_SESSION['church_name'] ?? '';

            $churchCode = $_SESSION['church_code'] ?? '';

            $churchType = $_SESSION['church_type'] ?? 'kids';

        }

    }



    $adminEmail = '';



    // If we have church_id but missing name/code/type/email, fetch from DB

    if ($churchId > 0 && (empty($churchName) || empty($churchType) || $churchType === 'kids')) {

        try {

            $conn = getDBConnection();

            // Ensure column exists

            ensureChurchTypeColumn($conn);

            $stmt = $conn->prepare("SELECT church_name, church_code, admin_email, COALESCE(church_type,'kids') AS church_type FROM churches WHERE id = ?");

            $stmt->bind_param("i", $churchId);

            $stmt->execute();

            if ($row = $stmt->get_result()->fetch_assoc()) {

                if (empty($churchName))

                    $churchName = $row['church_name'];

                if (empty($churchCode))

                    $churchCode = $row['church_code'];

                $churchType = $row['church_type'] ?? 'kids';

                $adminEmail = $row['admin_email'] ?? '';

                // Persist back to session

                $_SESSION['church_name'] = $churchName;

                $_SESSION['church_code'] = $churchCode;

                $_SESSION['church_type'] = $churchType;

            }

        } catch (Exception $e) {

            error_log("getSessionInfo DB error: " . $e->getMessage());

        }

    }



    if ($churchId > 0 && empty($adminEmail)) {

        try {

            $conn = getDBConnection();

            $stmt = $conn->prepare("SELECT admin_email FROM churches WHERE id = ?");

            $stmt->bind_param("i", $churchId);

            $stmt->execute();

            if ($row = $stmt->get_result()->fetch_assoc()) {

                $adminEmail = $row['admin_email'] ?? '';

            }

        } catch (Exception $e) {

            error_log("getSessionInfo admin_email error: " . $e->getMessage());

        }

    }



    if ($churchId === 0 && $uncleId === null) {

        sendJSON(['success' => false, 'message' => 'لا توجد جلسة نشطة']);

        return;

    }



    // Determine login type

    $loginType = isset($_SESSION['uncle_id']) ? 'uncle' : 'church';



    // For church direct logins, role is always 'admin'

    if ($loginType === 'church' && empty($uncleRole)) {

        $uncleRole = 'admin';

    }



    sendJSON([

        'success' => true,

        'church_id' => $churchId,

        'church_name' => $churchName,

        'church_code' => $churchCode,

        'church_type' => $churchType,

        'admin_email' => $adminEmail,

        'uncle_id' => $uncleId,

        'uncle_name' => $uncleName,

        'uncle_username' => $_SESSION['uncle_username'] ?? '',

        'username' => $_SESSION['uncle_username'] ?? '',

        // Return role under EVERY key JS might check

        'uncle_role' => $uncleRole,

        'uncleRole' => $uncleRole,

        'role' => $uncleRole,

        'login_type' => $loginType,

        'loginType' => $loginType,

    ]);

}

// Handle different actions

try {

    switch ($action) {



        case 'login':

            handleLogin();

            break;

        case 'auto_login':  // أضف هذا

            handleAutoLogin();

            break;



        case 'logout':

            session_destroy();

            sendJSON(['success' => true, 'message' => 'تم تسجيل الخروج بنجاح']);

            break;



        case 'getData':

            checkAuth();

            getData();

            break;



        case 'submitAttendance':

            checkAuth();

            submitAttendance();

            break;



        case 'updateCoupons':

            checkAuth();

            updateCoupons();

            break;



        case 'addStudent':

            checkAuth();

            addStudent();

            break;



        case 'updateStudent':

            checkAuth();

            updateStudent();

            break; // Added missing break



        case 'reviewStudentGender':

            checkAuth();

            reviewStudentGender();

            break;



        case 'uncleLogin':

            handleUncleLogin();

            break;



        case 'getCurrentUncle':

            getCurrentUncle();

            break;



        case 'updateUncleProfile':

            updateUncleProfile();

            break;



        case 'updateUncleImage':

            updateUncleImage();

            break;



        case 'getAllUncles':

            getAllUncles();

            break;



        case 'addUncle':

            addUncle();

            break;



        case 'updateUncle':

            updateUncle();

            break;



        case 'deleteUncle':

            deleteUncle();

            break;



        case 'updateChurchAdminEmail':

            updateChurchAdminEmail();

            break;



        case 'deleteStudent':

            checkAuth();

            deleteStudent();

            break;



        case 'updateStudentImage':

            checkAuth();

            updateStudentImage();

            break;



        case 'getAllAnnouncements':

            checkAuth();

            getAllAnnouncements();

            break;



        case 'addAnnouncement':

            checkAuth();

            addAnnouncement();

            break;



        case 'toggleAnnouncement':

            checkAuth();

            toggleAnnouncement();

            break;



        case 'deleteAnnouncement':

            checkAuth();

            deleteAnnouncement();

            break;



        case 'test':

            sendJSON([

                'success' => true,

                'message' => 'API is working!',

                'timestamp' => date('Y-m-d H:i:s')

            ]);

            break;



        case 'getStudentByPhone':

            getStudentByPhone();

            break;



        case 'getStudentAttendance':

            getStudentAttendance();

            break;



        case 'getAnnouncementsForStudent':

            getAnnouncementsForStudent();

            break;



        case 'getAllChurches':

            getAllChurches();

            break;



        case 'getPublicStats':

            getPublicStats();

            break;



        case 'submitRegistrationRequest':

            submitRegistrationRequest();

            break;



        case 'approveRegistration':

            checkAuth();

            approveRegistration();

            break;



        case 'getPendingRegistrations':

            checkAuth();

            getPendingRegistrations();

            break;



        case 'updateRegistration':

            checkAuth();

            updateRegistration();

            break;



        case 'bulkUpdateRegistrations':

            checkAuth();

            bulkUpdateRegistrations();

            break;



        case 'rejectRegistration':

            checkAuth();

            rejectRegistration();

            break;



        case 'addChurch':

            checkAuth();

            addChurch();

            break;



        case 'getAllChurchesForAdmin':

            checkAuth();

            getAllChurchesForAdmin();

            break;



        case 'updateChurch':

            checkAuth();

            updateChurch();

            break;



        case 'updateChurchPassword':

            checkAuth();

            updateChurchPassword();

            break;



        case 'deleteChurch':

            checkAuth();

            deleteChurch();

            break;



        case 'cleanupOrphanedFiles':

            cleanupOrphanedFiles();

            break;



        case 'generateKidsTemplate':

            generateKidsTemplate();

            break;



        case 'bulkAddKids':

            checkAuth();

            bulkAddKids();

            break;



        case 'getKidsData':

            checkAuth();

            getKidsData();

            break;



        case 'saveSiblingGroup':

            saveSiblingGroup();

            break;



        case 'kidLogin':

            handleKidLogin();

            break;



        case 'checkKidPasswordByPhone':

            checkKidPasswordByPhone();

            break;



        case 'kidLoginByPhoneWithPassword':

            kidLoginByPhoneWithPassword();

            break;



        case 'setupStudentPassword':

            setupStudentPassword();

            break;

        case 'changeStudentPassword':

            changeStudentPassword();

            break;



        case 'getStudentProfile':

            getStudentProfile();

            break;



        case 'updateStudentInfo':

            updateStudentInfo();

            break;



        case 'searchKidsByName':

            searchKidsByName();

            break;



        case 'updateStudentAttendance':

            updateStudentAttendance();

            break;



        case 'updateCouponsKids':

            updateCouponsKids();

            break;



        case 'updateStudentImageAfterCreation':

            checkAuth();

            updateStudentImageAfterCreation();

            break;



        case 'hasTempAttendance':

            hasTempAttendance();

            break;



        case 'getChurchStatistics':

            checkAuth();

            getChurchStatistics();

            break;



        case 'getClassDetails':

            checkAuth();

            getClassDetails();

            break;



        case 'getStudentAttendanceDetails':

            checkAuth();

            getStudentAttendanceDetails();

            break;



        case 'updateCouponsWithReason':

            checkAuth();

            updateCouponsWithReason();

            break;



        case 'getCouponLogs':

            checkAuth();

            getCouponLogs();

            break;



        case 'getChurchClasses':

            getChurchClasses();

            break;



        case 'saveChurchClasses':

            checkAuth();

            saveChurchClasses();

            break;



        case 'addChurchClass':

            checkAuth();

            addChurchClass();

            break;



        case 'updateChurchClass':

            checkAuth();

            updateChurchClass();

            break;



        case 'deleteChurchClass':

            checkAuth();

            deleteChurchClass();

            break;



        case 'resetChurchClasses':

            checkAuth();

            resetChurchClasses();

            break;



        case 'reorderChurchClasses':

            checkAuth();

            reorderChurchClasses();

            break;



        case 'getChurchClassesForAdmin':

            getChurchClassesForAdmin();

            break;



        case 'getPublicChurchClasses':

            getPublicChurchClasses();

            break;



        case 'getChurchClassesWithStats':

            checkAuth(); // يتطلب تسجيل دخول

            getChurchClassesWithStats();

            break;



        // دوال الرحلات / المؤتمرات

        case 'getTrips':

            getTrips();

            break;

        case 'getGuests':

            getGuests();

            break;

        case 'addGuest':

            addGuest();

            break;

        case 'updateGuest':

            updateGuest();

            break;

        case 'deleteGuest':

            deleteGuest();

            break;

        case 'transferGuestToStudent':

            transferGuestToStudent();

            break;



        case 'addTrip':

            addTrip();

            break;



        case 'updateTrip':

            updateTrip();

            break;



        case 'deleteTrip':

            deleteTrip();

            break;



        case 'sendCollaborationRequest':

            checkAuth();

            sendCollaborationRequest();

            break;



        case 'getCollaborationRequests':

            checkAuth();

            getCollaborationRequests();

            break;



        case 'respondToCollaborationRequest':

            checkAuth();

            respondToCollaborationRequest();

            break;

        case 'removeTripCollaborator':

            checkAuth();

            removeTripCollaborator();

            break;





        case 'getCustomFieldTemplates':

            checkAuth();

            getCustomFieldTemplates();

            break;

        case 'addCustomFieldTemplate':

            checkAuth();

            addCustomFieldTemplate();

            break;

        case 'deleteCustomFieldTemplate':

            checkAuth();

            deleteCustomFieldTemplate();

            break;



        case 'getTripDetails':

            getTripDetails();

            break;



        case 'processGameQRCode':

            // Processes a camera-scanned game URL: updates student's per-trip points JSON

            processGameQRCode();

            break;



        case 'updateTripPointsConfig':

            // Save a custom template / points config for a trip (JSON)

            checkAuth();

            updateTripPointsConfig();

            break;



        case 'registerStudentForTrip':

            registerStudentForTrip();

            break;



        case 'searchAllStudents':

            searchAllStudents();

            break;



        case 'addTripPayment':

            addTripPayment();

            break;



        case 'cancelTripRegistration':

            cancelTripRegistration();

            break;



        case 'exportTripData':

            exportTripData();

            break;



        case 'bulkUpdateCustomData':

            bulkUpdateCustomData();

            break;



        case 'getWaitlist':

            getWaitlistAction();

            break;



        case 'removeFromWaitlist':

            removeFromWaitlist();

            break;



        case 'rebalanceTripWaitlist':

            rebalanceTripWaitlist();

            break;



        case 'addTripWaitlistPayment':

            addTripWaitlistPayment();

            break;



        case 'deleteTripWaitlistPayment':

            deleteTripWaitlistPayment();

            break;



        case 'restoreTripWaitlistPayment':

            restoreTripWaitlistPayment();

            break;





        // دوال تحسين الحضور

        case 'getFridaysInMonth':

            getFridaysInMonth();

            break;



        case 'getAttendanceByDateAndClass':

            getAttendanceByDateAndClass();

            break;



        case 'updateSingleAttendance':

            updateSingleAttendance();

            break;



        case 'deleteAttendance':

            deleteAttendance();

            break;



        // دوال إعدادات الفصول

        case 'getClassSettings':

            getClassSettings();

            break;



        case 'saveClassSettings':

            saveClassSettings();

            break;



        // دوال تحديث الأطفال

        case 'updateStudentFull':

            updateStudentFull();

            break;



        case 'getTripExpenses':

            getTripExpenses();

            break;



        case 'saveTripExpense':

            saveTripExpense();

            break;



        case 'deleteTripExpense':

            deleteTripExpense();

            break;



        case 'getAttendanceByDate':

            getAttendanceByDate();

            break;



        case 'submitUncleAttendance':

            checkUncleAuth();

            submitUncleAttendance();

            break;



        case 'getUncleAttendanceByDate':

            checkUncleAuth();

            getUncleAttendanceByDate();

            break;



        case 'getUncleAttendanceReport':

            checkUncleAuth();

            getUncleAttendanceReport();

            break;



        case 'toggleUncleAttendance':

            checkUncleAuth();

            toggleUncleAttendance();

            break;



        case 'deleteUncleAttendance':

            checkUncleAuth();

            deleteUncleAttendance();

            break;



        case 'getSessionInfo':

            getSessionInfo();

            break;



        // ===== NEW AUDIT LOG FUNCTIONS =====

        case 'getAuditLogs':

            checkAuth();

            getAuditLogs();

            break;



        case 'getEntityAuditHistory':

            checkAuth();

            getEntityAuditHistory();

            break;



        case 'restore_session':

            // NOTE: session_start() already called at top of file — do NOT call again

            // Do NOT wipe $_SESSION — just overwrite the keys we need



            if (isset($_POST['church_code'])) {

                $church_code = sanitize($_POST['church_code']);



                $conn = getDBConnection();

                $stmt = $conn->prepare("SELECT id, church_name, church_code FROM churches WHERE church_code = ?");

                $stmt->bind_param("s", $church_code);

                $stmt->execute();

                $result = $stmt->get_result();



                if ($row = $result->fetch_assoc()) {

                    $_SESSION['church_id'] = $row['id'];

                    $_SESSION['church_name'] = $row['church_name'];

                    $_SESSION['church_code'] = $row['church_code'];

                    $_SESSION['permanent'] = true;



                    error_log("Session restored for church: " . $row['church_name']);

                    echo json_encode(['success' => true]);

                } else {

                    error_log("Church not found with code: " . $church_code);

                    echo json_encode(['success' => false, 'message' => 'Church not found']);

                }

            } elseif (isset($_POST['username'])) {

                $username = sanitize($_POST['username']);



                $conn = getDBConnection();

                $stmt = $conn->prepare("

                    SELECT u.id, u.name, u.username, u.role, u.church_id, 

                           c.church_name, c.church_code

                    FROM uncles u

                    LEFT JOIN churches c ON u.church_id = c.id

                    WHERE u.username = ? AND (u.deleted IS NULL OR u.deleted = 0)

                ");

                $stmt->bind_param("s", $username);

                $stmt->execute();

                $result = $stmt->get_result();



                if ($row = $result->fetch_assoc()) {

                    $_SESSION['uncle_id'] = $row['id'];

                    $_SESSION['uncle_name'] = $row['name'];

                    $_SESSION['uncle_username'] = $row['username'];

                    $_SESSION['uncle_role'] = $row['role'];

                    $_SESSION['church_id'] = $row['church_id'];

                    $_SESSION['church_name'] = $row['church_name'];

                    $_SESSION['church_code'] = $row['church_code'];

                    $_SESSION['permanent'] = true;



                    error_log("Session restored for uncle: " . $row['username']);

                    echo json_encode(['success' => true]);

                } else {

                    error_log("Uncle not found with username: " . $username);

                    echo json_encode(['success' => false, 'message' => 'User not found']);

                }

            } else {

                echo json_encode(['success' => false, 'message' => 'No credentials provided']);

            }

            break;



        default:

            sendJSON(['success' => false, 'message' => 'Invalid action: ' . $action]);

    }

} catch (Exception $e) {

    error_log("API Error: " . $e->getMessage());

    sendJSON(['success' => false, 'message' => 'خطأ في السيرفر: ' . $e->getMessage()]);

}

function getClassUncles()

{

    try {

        $churchId = getChurchId();

        $className = sanitize($_POST['class'] ?? '');



        if (empty($className)) {

            sendJSON(['success' => true, 'uncles' => []]);

            return;

        }



        $conn = getDBConnection();



        // Ensure table exists (safe guard)

        $conn->query("CREATE TABLE IF NOT EXISTS `uncle_class_assignments` (

            `id`         INT AUTO_INCREMENT PRIMARY KEY,

            `uncle_id`   INT NOT NULL,

            `church_id`  INT NOT NULL,

            `class_name` VARCHAR(100) NOT NULL,

            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            UNIQUE KEY `uq_uncle_class` (`uncle_id`, `church_id`, `class_name`)

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");



        $stmt = $conn->prepare("

            SELECT u.id, u.name, u.image_url, u.role, u.username

            FROM uncle_class_assignments a

            JOIN uncles u ON a.uncle_id = u.id

            WHERE a.church_id = ? AND a.class_name = ?

              AND (u.deleted IS NULL OR u.deleted = 0)

            ORDER BY

                CASE u.role

                    WHEN 'admin' THEN 1

                    WHEN 'developer' THEN 2

                    ELSE 3

                END, u.name

        ");

        $stmt->bind_param("is", $churchId, $className);

        $stmt->execute();

        $uncles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);



        sendJSON(['success' => true, 'uncles' => $uncles]);



    } catch (Exception $e) {

        error_log("getClassUncles error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في جلب معلمي الفصل']);

    }

}



/**

 * Assign an uncle to a class.

 * POST: uncleId, class (class name)

 */

function assignUncleToClass()

{

    try {

        $churchId = getChurchId();

        $uncleId = intval($_POST['uncleId'] ?? 0);

        $className = sanitize($_POST['class'] ?? '');



        if (!$uncleId || empty($className)) {

            sendJSON(['success' => false, 'message' => 'بيانات ناقصة']);

            return;

        }



        $conn = getDBConnection();



        // Ensure table exists

        $conn->query("CREATE TABLE IF NOT EXISTS `uncle_class_assignments` (

            `id`         INT AUTO_INCREMENT PRIMARY KEY,

            `uncle_id`   INT NOT NULL,

            `church_id`  INT NOT NULL,

            `class_name` VARCHAR(100) NOT NULL,

            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            UNIQUE KEY `uq_uncle_class` (`uncle_id`, `church_id`, `class_name`)

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");



        // Verify uncle belongs to this church

        $checkStmt = $conn->prepare("

            SELECT id FROM uncles

            WHERE id = ? AND church_id = ?

              AND (deleted IS NULL OR deleted = 0)

        ");

        $checkStmt->bind_param("ii", $uncleId, $churchId);

        $checkStmt->execute();

        if ($checkStmt->get_result()->num_rows === 0) {

            sendJSON(['success' => false, 'message' => 'المعلم غير موجود في هذه الكنيسة']);

            return;

        }



        $stmt = $conn->prepare("

            INSERT IGNORE INTO uncle_class_assignments (uncle_id, church_id, class_name)

            VALUES (?, ?, ?)

        ");

        $stmt->bind_param("iis", $uncleId, $churchId, $className);



        if ($stmt->execute()) {

            sendJSON(['success' => true, 'message' => 'تم تعيين المعلم للفصل']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في التعيين: ' . $stmt->error]);

        }



    } catch (Exception $e) {

        error_log("assignUncleToClass error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تعيين المعلم: ' . $e->getMessage()]);

    }

}



/**

 * Remove an uncle from a class assignment.

 * POST: uncleId, class (class name)

 */

function removeUncleFromClass()

{

    try {

        $churchId = getChurchId();

        $uncleId = intval($_POST['uncleId'] ?? 0);

        $className = sanitize($_POST['class'] ?? '');



        if (!$uncleId || empty($className)) {

            sendJSON(['success' => false, 'message' => 'بيانات ناقصة']);

            return;

        }



        $conn = getDBConnection();



        $stmt = $conn->prepare("

            DELETE FROM uncle_class_assignments

            WHERE uncle_id = ? AND church_id = ? AND class_name = ?

        ");

        $stmt->bind_param("iis", $uncleId, $churchId, $className);



        if ($stmt->execute()) {

            sendJSON(['success' => true, 'message' => 'تم إزالة المعلم من الفصل']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في الإزالة: ' . $stmt->error]);

        }



    } catch (Exception $e) {

        error_log("removeUncleFromClass error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في إزالة المعلم: ' . $e->getMessage()]);

    }

}

// ===== GET UNCLE CLASSES =====

function getUncleClasses($uncleId)

{

    $conn = getDBConnection();

    $stmt = $conn->prepare("

        SELECT class_name

        FROM uncle_class_assignments

        WHERE uncle_id = ?

        ORDER BY class_name

    ");

    $stmt->bind_param("i", $uncleId);

    $stmt->execute();

    $result = $stmt->get_result();



    $classes = [];

    while ($row = $result->fetch_assoc()) {

        $classes[] = ['class_name' => $row['class_name']];

    }

    return $classes;

}



// ===== SAVE UNCLE CLASSES =====

function saveUncleClasses($uncleId, $churchId, $classes)

{

    $conn = getDBConnection();



    // Delete existing classes

    $deleteStmt = $conn->prepare("DELETE FROM uncle_class_assignments WHERE uncle_id = ?");

    $deleteStmt->bind_param("i", $uncleId);

    $deleteStmt->execute();



    // Insert new classes

    if (!empty($classes) && is_array($classes)) {

        $insertStmt = $conn->prepare("

            INSERT INTO uncle_class_assignments (uncle_id, church_id, class_name) 

            VALUES (?, ?, ?)

        ");



        foreach ($classes as $class) {

            if (!empty($class)) {

                $insertStmt->bind_param("iis", $uncleId, $churchId, $class);

                $insertStmt->execute();

            }

        }

    }



    return true;

}



// ===== CLASS GRADE ORDER & ANNUAL GRADE-UP =====



function ensureChurchClassesOrderColumn($conn): void

{

    $conn->query("

        ALTER TABLE church_classes

        ADD COLUMN IF NOT EXISTS `order` INT NOT NULL DEFAULT 0

        COMMENT 'Grade sequence (lowest = youngest)'

        AFTER display_order

    ");

}



function ensureChurchSettingsAutoGradeColumns($conn): void

{

    $conn->query("ALTER TABLE church_settings ADD COLUMN IF NOT EXISTS

        `auto_grade_month` TINYINT UNSIGNED NULL DEFAULT NULL

        COMMENT '1-12; month for annual grade-up'");

    $conn->query("ALTER TABLE church_settings ADD COLUMN IF NOT EXISTS

        `auto_grade_day` TINYINT UNSIGNED NULL DEFAULT NULL

        COMMENT '1-31; day for annual grade-up'");

    $conn->query("ALTER TABLE church_settings ADD COLUMN IF NOT EXISTS

        `last_auto_grade_year` SMALLINT UNSIGNED NULL DEFAULT NULL

        COMMENT 'Last year auto grade-up ran'");

}



/**

 * Ordered class list for a church (youngest → oldest).

 * Uses church_classes.`order` when custom; global classes use display_order.

 */

function getOrderedClassListForChurch(int $churchId): array

{

    $conn = getDBConnection();

    ensureChurchClassesOrderColumn($conn);



    $chk = $conn->prepare("SELECT COUNT(*) AS cnt FROM church_classes WHERE church_id = ? AND is_active = 1");

    $chk->bind_param("i", $churchId);

    $chk->execute();

    $hasCustom = (int) $chk->get_result()->fetch_assoc()['cnt'] > 0;



    if ($hasCustom) {

        $stmt = $conn->prepare("

            SELECT id, arabic_name, code,

                   COALESCE(NULLIF(`order`, 0), display_order) AS sort_order

            FROM church_classes

            WHERE church_id = ? AND is_active = 1

            ORDER BY sort_order ASC, arabic_name ASC

        ");

        $stmt->bind_param("i", $churchId);

    } else {

        $stmt = $conn->prepare("

            SELECT id, arabic_name, code, display_order AS sort_order

            FROM classes

            ORDER BY display_order ASC, arabic_name ASC

        ");

    }

    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

}



/**

 * Promote active students to next class; highest class → خريجين.

 */

function gradeUpStudentsForChurch(int $churchId): array

{

    $conn = getDBConnection();

    ensureStudentGraduateSchema($conn);

    $ordered = getOrderedClassListForChurch($churchId);

    if (count($ordered) < 1) {

        return [

            'promoted' => 0,

            'graduated' => 0,

            'unchanged' => 0,

            'message' => 'يجب وجود فصل واحد على الأقل',

        ];

    }



    $topClassId = (int) $ordered[count($ordered) - 1]['id'];

    $nextById = [];

    for ($i = 0; $i < count($ordered) - 1; $i++) {

        $nextById[(int) $ordered[$i]['id']] = $ordered[$i + 1];

    }



    $stmt = $conn->prepare("

        SELECT id, class_id FROM students

        WHERE church_id = ? AND COALESCE(enrollment_status, 'active') = 'active'

    ");

    $stmt->bind_param("i", $churchId);

    $stmt->execute();

    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);



    $upd = $conn->prepare("

        UPDATE students

        SET class_id = ?, class = ?, enrollment_status = 'active', updated_at = NOW()

        WHERE id = ? AND church_id = ?

    ");

    $topClassName = $ordered[count($ordered) - 1]['arabic_name'] ?? '';

    $gradStmt = $conn->prepare("

        UPDATE students

        SET graduate_from_class_id = class_id,

            graduate_from_class = ?,

            class_id = 0,

            class = 'خريجين',

            enrollment_status = 'graduate',

            updated_at = NOW()

        WHERE id = ? AND church_id = ? AND class_id = ?

    ");



    $promoted = 0;

    $graduated = 0;

    $unchanged = 0;

    foreach ($students as $s) {

        $sid = (int) $s['id'];

        $cid = (int) $s['class_id'];



        if ($cid === $topClassId) {

            $gradStmt->bind_param("siii", $topClassName, $sid, $churchId, $topClassId);

            if ($gradStmt->execute() && $gradStmt->affected_rows > 0) {

                $graduated++;

            } else {

                $unchanged++;

            }

            continue;

        }



        if (!isset($nextById[$cid])) {

            $unchanged++;

            continue;

        }

        $next = $nextById[$cid];

        $nextId = (int) $next['id'];

        $nextName = $next['arabic_name'];

        $upd->bind_param("isii", $nextId, $nextName, $sid, $churchId);

        if ($upd->execute() && $upd->affected_rows > 0) {

            $promoted++;

        } else {

            $unchanged++;

        }

    }



    return [

        'promoted' => $promoted,

        'graduated' => $graduated,

        'unchanged' => $unchanged,

        'top_class' => $ordered[count($ordered) - 1]['arabic_name'] ?? '',

    ];

}



/**

 * Run scheduled grade-up for all churches whose date has passed this year.

 * Safe to call on login / session restore (idempotent per calendar year).

 */

function maybeRunScheduledGradeUps($conn): void

{

    try {

        ensureChurchSettingsTable($conn);

        ensureChurchSettingsAutoGradeColumns($conn);



        $now = new DateTime('today');

        $year = (int) $now->format('Y');

        $month = (int) $now->format('n');

        $day = (int) $now->format('j');



        $res = $conn->query("

            SELECT church_id, auto_grade_month, auto_grade_day, last_auto_grade_year

            FROM church_settings

            WHERE auto_grade_month IS NOT NULL

              AND auto_grade_day IS NOT NULL

              AND auto_grade_month BETWEEN 1 AND 12

              AND auto_grade_day BETWEEN 1 AND 31

        ");

        if (!$res) {

            return;

        }



        $markStmt = $conn->prepare("

            UPDATE church_settings SET last_auto_grade_year = ? WHERE church_id = ?

        ");



        while ($row = $res->fetch_assoc()) {

            $churchId = (int) $row['church_id'];

            $targetMonth = (int) $row['auto_grade_month'];

            $targetDay = (int) $row['auto_grade_day'];

            $lastYear = (int) ($row['last_auto_grade_year'] ?? 0);



            if ($lastYear >= $year) {

                continue;

            }

            if ($month < $targetMonth || ($month === $targetMonth && $day < $targetDay)) {

                continue;

            }



            $result = gradeUpStudentsForChurch($churchId);

            if (($result['promoted'] ?? 0) > 0 || ($result['graduated'] ?? 0) > 0 || ($result['unchanged'] ?? 0) > 0) {

                $markStmt->bind_param("ii", $year, $churchId);

                $markStmt->execute();

                writeAuditLog(

                    'auto_grade_up',

                    'students',

                    $churchId,

                    'نقل سنوي تلقائي للفصول',

                    null,

                    null,

                    json_encode($result, JSON_UNESCAPED_UNICODE)

                );

            }

        }

    } catch (Exception $e) {

        error_log('maybeRunScheduledGradeUps: ' . $e->getMessage());

    }

}



function runBackgroundGradeUpChecks(): void

{

    if (!empty($_SESSION['_grade_up_scheduled_checked'])) {

        return;

    }

    $_SESSION['_grade_up_scheduled_checked'] = true;

    try {

        $conn = getDBConnection();

        maybeRunScheduledGradeUps($conn);

    } catch (Exception $e) {

        error_log('runBackgroundGradeUpChecks: ' . $e->getMessage());

    }

}



function gradeUpAllKids()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        if (!$churchId) {

            sendJSON(['success' => false, 'message' => 'معرف الكنيسة مطلوب']);

            return;

        }



        $result = gradeUpStudentsForChurch($churchId);

        if (isset($result['message'])) {

            sendJSON(['success' => false, 'message' => $result['message']]);

            return;

        }



        writeAuditLog(

            'manual_grade_up',

            'students',

            $churchId,

            'نقل يدوي للفصول — سنة دراسية جديدة',

            null,

            null,

            json_encode($result, JSON_UNESCAPED_UNICODE)

        );



        $msg = "تم نقل {$result['promoted']} طفل إلى الفصل التالي";

        if (!empty($result['graduated'])) {

            $msg .= " — و{$result['graduated']} إلى قسم الخريجين";

        }

        if (!empty($result['unchanged'])) {

            $msg .= " ({$result['unchanged']} بدون تغيير)";

        }



        sendJSON([

            'success' => true,

            'message' => $msg,

            'promoted' => $result['promoted'],

            'graduated' => $result['graduated'] ?? 0,

            'unchanged' => $result['unchanged'],

            'top_class' => $result['top_class'] ?? '',

        ]);

    } catch (Exception $e) {

        error_log('gradeUpAllKids: ' . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في نقل الفصول']);

    }

}



// ===== GRADUATES (خريجين) & CROSS-CHURCH TRANSFERS =====



function ensureStudentGraduateSchema($conn): void

{

    $conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS

        `enrollment_status` VARCHAR(20) NOT NULL DEFAULT 'active'

        COMMENT 'active | graduate'");

    $conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS

        `graduate_from_class_id` INT NULL DEFAULT NULL

        COMMENT 'Class id before graduating'");

    $conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS

        `graduate_from_class` VARCHAR(100) NULL DEFAULT NULL

        COMMENT 'Class name before graduating'");

    $conn->query("

        CREATE TABLE IF NOT EXISTS `student_transfer_requests` (

            `id` int(11) NOT NULL AUTO_INCREMENT,

            `from_church_id` int(11) NOT NULL,

            `to_church_id` int(11) NOT NULL,

            `from_student_id` int(11) DEFAULT NULL,

            `student_name` varchar(100) NOT NULL DEFAULT '',

            `student_snapshot` longtext NOT NULL,

            `status` enum('pending','accepted','rejected') NOT NULL DEFAULT 'pending',

            `target_class_id` int(11) DEFAULT NULL,

            `accepted_student_id` int(11) DEFAULT NULL,

            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),

            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),

            PRIMARY KEY (`id`),

            KEY `idx_str_to_pending` (`to_church_id`,`status`),

            KEY `idx_str_from` (`from_church_id`,`status`)

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci

    ");

}



function getFirstClassIdForChurch(int $churchId): int

{

    $ordered = getOrderedClassListForChurch($churchId);

    return !empty($ordered[0]['id']) ? (int) $ordered[0]['id'] : 0;

}



function purgeStudentRelatedRows($conn, int $studentId): void

{

    $deleteAttendanceStmt = $conn->prepare("DELETE FROM attendance WHERE student_id = ?");

    $deleteAttendanceStmt->bind_param("i", $studentId);

    $deleteAttendanceStmt->execute();



    $tableCheck = $conn->query("SHOW TABLES LIKE 'coupon_logs'");

    if ($tableCheck && $tableCheck->num_rows > 0) {

        $deleteLogsStmt = $conn->prepare("DELETE FROM coupon_logs WHERE student_id = ?");

        $deleteLogsStmt->bind_param("i", $studentId);

        $deleteLogsStmt->execute();

    }



    if ($conn->query("SHOW TABLES LIKE 'student_sibling_group_members'")->num_rows > 0) {

        $sg = $conn->prepare("DELETE FROM student_sibling_group_members WHERE student_id = ?");

        $sg->bind_param("i", $studentId);

        $sg->execute();

    }

}



function removeStudentFromChurch($conn, int $studentId, int $churchId): bool

{

    purgeStudentRelatedRows($conn, $studentId);

    $deleteStmt = $conn->prepare("DELETE FROM students WHERE id = ? AND church_id = ?");

    $deleteStmt->bind_param("ii", $studentId, $churchId);

    $deleteStmt->execute();

    return $deleteStmt->affected_rows > 0;

}



/**

 * Resolve which class a graduate should return to (by saved id, then by saved name).

 */

function resolveGraduateRestoreClass($conn, int $churchId, int $classId, string $className): ?array

{

    if ($classId > 0) {

        $cc = $conn->prepare("

            SELECT id, arabic_name FROM church_classes

            WHERE id = ? AND church_id = ? AND is_active = 1 LIMIT 1

        ");

        $cc->bind_param("ii", $classId, $churchId);

        $cc->execute();

        if ($row = $cc->get_result()->fetch_assoc()) {

            return ['id' => (int) $row['id'], 'name' => $row['arabic_name']];

        }

        $gc = $conn->prepare("SELECT id, arabic_name FROM classes WHERE id = ? LIMIT 1");

        $gc->bind_param("i", $classId);

        $gc->execute();

        if ($row = $gc->get_result()->fetch_assoc()) {

            return ['id' => (int) $row['id'], 'name' => $row['arabic_name']];

        }

    }



    $name = trim($className);

    if ($name !== '' && $name !== 'خريجين') {

        $byName = $conn->prepare("

            SELECT id, arabic_name FROM church_classes

            WHERE church_id = ? AND arabic_name = ? AND is_active = 1 LIMIT 1

        ");

        $byName->bind_param("is", $churchId, $name);

        $byName->execute();

        if ($row = $byName->get_result()->fetch_assoc()) {

            return ['id' => (int) $row['id'], 'name' => $row['arabic_name']];

        }

        $gByName = $conn->prepare("SELECT id, arabic_name FROM classes WHERE arabic_name = ? LIMIT 1");

        $gByName->bind_param("s", $name);

        $gByName->execute();

        if ($row = $gByName->get_result()->fetch_assoc()) {

            return ['id' => (int) $row['id'], 'name' => $row['arabic_name']];

        }

    }



    return null;

}



function restoreGraduateToClass()

{

    try {

        $churchId = getChurchId();

        $studentId = intval($_POST['studentId'] ?? 0);

        if (!$churchId || !$studentId) {

            sendJSON(['success' => false, 'message' => 'بيانات غير كاملة']);

            return;

        }



        $conn = getDBConnection();

        ensureStudentGraduateSchema($conn);



        $stmt = $conn->prepare("

            SELECT id, name, graduate_from_class_id, graduate_from_class

            FROM students

            WHERE id = ? AND church_id = ? AND enrollment_status = 'graduate'

            LIMIT 1

        ");

        $stmt->bind_param("ii", $studentId, $churchId);

        $stmt->execute();

        $student = $stmt->get_result()->fetch_assoc();

        if (!$student) {

            sendJSON(['success' => false, 'message' => 'الخريج غير موجود']);

            return;

        }



        $fromId = (int) ($student['graduate_from_class_id'] ?? 0);

        $fromName = trim($student['graduate_from_class'] ?? '');

        $target = resolveGraduateRestoreClass($conn, $churchId, $fromId, $fromName);



        if (!$target) {

            sendJSON([

                'success' => false,

                'message' => 'لا يمكن تحديد الفصل السابق'

                    . ($fromName ? ' («' . $fromName . '»)' : '')

                    . ' — تأكد أن الفصل ما زال موجوداً',

            ]);

            return;

        }



        $upd = $conn->prepare("

            UPDATE students

            SET class_id = ?,

                class = ?,

                enrollment_status = 'active',

                graduate_from_class_id = NULL,

                graduate_from_class = NULL,

                updated_at = NOW()

            WHERE id = ? AND church_id = ?

        ");

        $upd->bind_param("isii", $target['id'], $target['name'], $studentId, $churchId);

        if ($upd->execute() && $upd->affected_rows > 0) {

            writeAuditLog(

                'restore_graduate',

                'students',

                $studentId,

                $student['name'],

                null,

                null,

                'class:' . $target['name']

            );

            sendJSON([

                'success' => true,

                'message' => 'تمت إعادة ' . $student['name'] . ' إلى فصل «' . $target['name'] . '»',

                'class_name' => $target['name'],

            ]);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في إعادة الطفل للفصل']);

        }

    } catch (Exception $e) {

        error_log('restoreGraduateToClass: ' . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في إعادة الطفل للفصل']);

    }

}



function getChurchesForTransfer()

{

    try {

        $churchId = getChurchId();

        if (!$churchId) {

            sendJSON(['success' => false, 'message' => 'معرف الكنيسة مطلوب']);

            return;

        }

        $conn = getDBConnection();

        $stmt = $conn->prepare("

            SELECT id, church_name, church_code

            FROM churches

            WHERE id != ?

            ORDER BY church_name ASC

        ");

        $stmt->bind_param("i", $churchId);

        $stmt->execute();

        $churches = [];

        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {

            $churches[] = [

                'id' => (int) $row['id'],

                'name' => $row['church_name'],

                'code' => $row['church_code'],

            ];

        }

        sendJSON(['success' => true, 'churches' => $churches]);

    } catch (Exception $e) {

        error_log('getChurchesForTransfer: ' . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في جلب الكنائس']);

    }

}



function getGraduates()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        if (!$churchId) {

            sendJSON(['success' => false, 'message' => 'معرف الكنيسة مطلوب']);

            return;

        }

        $conn = getDBConnection();

        ensureStudentGraduateSchema($conn);



        $stmt = $conn->prepare("

            SELECT s.id, s.name, s.phone, s.birthday, s.coupons, s.custom_info, s.image_url,

                   s.gender, s.address, s.emergency_phone, s.medical_notes, s.class_id,

                   s.graduate_from_class_id, s.graduate_from_class,

                   s.created_at, s.updated_at

            FROM students s

            WHERE s.church_id = ? AND s.enrollment_status = 'graduate'

            ORDER BY s.name

        ");

        $stmt->bind_param("i", $churchId);

        $stmt->execute();

        $graduates = [];

        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {

            $fromId = (int) ($row['graduate_from_class_id'] ?? 0);

            $fromName = trim($row['graduate_from_class'] ?? '');

            $canRestore = resolveGraduateRestoreClass($conn, $churchId, $fromId, $fromName) !== null;

            $row['can_restore'] = $canRestore;

            $row['previous_class'] = $fromName ?: null;

            $graduates[] = $row;

        }



        $inStmt = $conn->prepare("

            SELECT r.id, r.student_name, r.status, r.created_at,

                   fc.church_name AS from_church_name

            FROM student_transfer_requests r

            JOIN churches fc ON fc.id = r.from_church_id

            WHERE r.to_church_id = ? AND r.status = 'pending'

            ORDER BY r.created_at DESC

        ");

        $inStmt->bind_param("i", $churchId);

        $inStmt->execute();

        $incoming = $inStmt->get_result()->fetch_all(MYSQLI_ASSOC);



        $outStmt = $conn->prepare("

            SELECT r.id, r.student_name, r.status, r.created_at,

                   tc.church_name AS to_church_name

            FROM student_transfer_requests r

            JOIN churches tc ON tc.id = r.to_church_id

            WHERE r.from_church_id = ? AND r.status = 'pending'

            ORDER BY r.created_at DESC

        ");

        $outStmt->bind_param("i", $churchId);

        $outStmt->execute();

        $outgoing = $outStmt->get_result()->fetch_all(MYSQLI_ASSOC);



        $classes = getOrderedClassListForChurch($churchId);

        $defaultClassId = getFirstClassIdForChurch($churchId);



        sendJSON([

            'success' => true,

            'graduates' => $graduates,

            'incoming_transfers' => $incoming,

            'outgoing_transfers' => $outgoing,

            'classes' => $classes,

            'default_class_id' => $defaultClassId,

        ]);

    } catch (Exception $e) {

        error_log('getGraduates: ' . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحميل الخريجين']);

    }

}



function deleteGraduateStudent()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $studentId = intval($_POST['studentId'] ?? 0);

        if (!$churchId || !$studentId) {

            sendJSON(['success' => false, 'message' => 'بيانات غير كاملة']);

            return;

        }



        $conn = getDBConnection();

        ensureStudentGraduateSchema($conn);

        $chk = $conn->prepare("SELECT name FROM students WHERE id = ? AND church_id = ? AND enrollment_status = 'graduate' LIMIT 1");

        $chk->bind_param("ii", $studentId, $churchId);

        $chk->execute();

        $row = $chk->get_result()->fetch_assoc();

        if (!$row) {

            sendJSON(['success' => false, 'message' => 'الطفل غير موجود في قسم الخريجين']);

            return;

        }



        $conn->begin_transaction();

        if (removeStudentFromChurch($conn, $studentId, $churchId)) {

            $conn->commit();

            writeAuditLog('delete_graduate', 'students', $studentId, $row['name']);

            sendJSON(['success' => true, 'message' => 'تم حذف بيانات الخريج']);

        } else {

            $conn->rollback();

            sendJSON(['success' => false, 'message' => 'فشل الحذف']);

        }

    } catch (Exception $e) {

        if (isset($conn))

            $conn->rollback();

        error_log('deleteGraduateStudent: ' . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في الحذف']);

    }

}



function exportGraduateStudent()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $studentId = intval($_REQUEST['studentId'] ?? $_POST['studentId'] ?? 0);

        if (!$churchId || !$studentId) {

            sendJSON(['success' => false, 'message' => 'بيانات غير كاملة']);

            return;

        }



        $conn = getDBConnection();

        ensureStudentGraduateSchema($conn);

        $stmt = $conn->prepare("SELECT * FROM students WHERE id = ? AND church_id = ? AND enrollment_status = 'graduate' LIMIT 1");

        $stmt->bind_param("ii", $studentId, $churchId);

        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {

            sendJSON(['success' => false, 'message' => 'الطفل غير موجود في قسم الخريجين']);

            return;

        }

        unset($row['password_hash']);



        header('Content-Type: application/json; charset=utf-8');

        header('Content-Disposition: attachment; filename="graduate_' . $studentId . '_' . date('Y-m-d') . '.json"');

        echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        exit;

    } catch (Exception $e) {

        error_log('exportGraduateStudent: ' . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في التصدير']);

    }

}



function sendGraduateToChurch()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $studentId = intval($_POST['studentId'] ?? 0);

        $toChurchCode = sanitize($_POST['to_church_code'] ?? '');



        if (!$churchId || !$studentId || empty($toChurchCode)) {

            sendJSON(['success' => false, 'message' => 'بيانات غير كاملة']);

            return;

        }



        $conn = getDBConnection();

        ensureStudentGraduateSchema($conn);



        $stmt = $conn->prepare("SELECT * FROM students WHERE id = ? AND church_id = ? AND enrollment_status = 'graduate' LIMIT 1");

        $stmt->bind_param("ii", $studentId, $churchId);

        $stmt->execute();

        $student = $stmt->get_result()->fetch_assoc();

        if (!$student) {

            sendJSON(['success' => false, 'message' => 'الطفل غير موجود في قسم الخريجين']);

            return;

        }



        $cStmt = $conn->prepare("SELECT id, church_name FROM churches WHERE church_code = ? LIMIT 1");

        $cStmt->bind_param("s", $toChurchCode);

        $cStmt->execute();

        $toChurch = $cStmt->get_result()->fetch_assoc();

        if (!$toChurch) {

            sendJSON(['success' => false, 'message' => 'رمز الكنيسة غير صحيح']);

            return;

        }

        $toChurchId = (int) $toChurch['id'];

        if ($toChurchId === $churchId) {

            sendJSON(['success' => false, 'message' => 'لا يمكن الإرسال لنفس الكنيسة']);

            return;

        }



        $dup = $conn->prepare("

            SELECT id FROM student_transfer_requests

            WHERE from_student_id = ? AND to_church_id = ? AND status = 'pending' LIMIT 1

        ");

        $dup->bind_param("ii", $studentId, $toChurchId);

        $dup->execute();

        if ($dup->get_result()->num_rows > 0) {

            sendJSON(['success' => false, 'message' => 'يوجد طلب معلق بالفعل لهذا الطفل']);

            return;

        }



        unset($student['password_hash']);

        $snapshotJson = json_encode($student, JSON_UNESCAPED_UNICODE);

        $studentName = $student['name'] ?? '';



        $conn->begin_transaction();

        $ins = $conn->prepare("

            INSERT INTO student_transfer_requests

                (from_church_id, to_church_id, from_student_id, student_name, student_snapshot, status)

            VALUES (?, ?, ?, ?, ?, 'pending')

        ");

        $ins->bind_param("iiiss", $churchId, $toChurchId, $studentId, $studentName, $snapshotJson);

        if (!$ins->execute()) {

            $conn->rollback();

            sendJSON(['success' => false, 'message' => 'فشل إنشاء طلب النقل']);

            return;

        }



        if (!removeStudentFromChurch($conn, $studentId, $churchId)) {

            $conn->rollback();

            sendJSON(['success' => false, 'message' => 'فشل إزالة سجل الخريج']);

            return;

        }



        $conn->commit();

        writeAuditLog('graduate_transfer_send', 'students', $studentId, $studentName, null, null, 'to:' . $toChurch['church_name']);

        sendJSON([

            'success' => true,

            'message' => 'تم إرسال الطلب إلى ' . $toChurch['church_name'] . ' — بانتظار الموافقة',

        ]);

    } catch (Exception $e) {

        if (isset($conn))

            $conn->rollback();

        error_log('sendGraduateToChurch: ' . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في إرسال الطلب']);

    }

}



function respondGraduateTransfer()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $requestId = intval($_POST['request_id'] ?? 0);

        $decision = sanitize($_POST['decision'] ?? '');

        $classId = intval($_POST['class_id'] ?? 0);



        if (!$churchId || !$requestId || !in_array($decision, ['accept', 'reject'], true)) {

            sendJSON(['success' => false, 'message' => 'بيانات غير صالحة']);

            return;

        }



        $conn = getDBConnection();

        ensureStudentGraduateSchema($conn);



        $rStmt = $conn->prepare("

            SELECT * FROM student_transfer_requests

            WHERE id = ? AND to_church_id = ? AND status = 'pending' LIMIT 1

        ");

        $rStmt->bind_param("ii", $requestId, $churchId);

        $rStmt->execute();

        $req = $rStmt->get_result()->fetch_assoc();

        if (!$req) {

            sendJSON(['success' => false, 'message' => 'الطلب غير موجود أو تمت معالجته']);

            return;

        }



        if ($decision === 'reject') {

            $upd = $conn->prepare("UPDATE student_transfer_requests SET status = 'rejected', updated_at = NOW() WHERE id = ?");

            $upd->bind_param("i", $requestId);

            $upd->execute();

            writeAuditLog('graduate_transfer_reject', 'students', $requestId, $req['student_name'] ?? '');

            sendJSON(['success' => true, 'message' => 'تم رفض طلب النقل']);

            return;

        }



        if ($classId <= 0) {

            $classId = getFirstClassIdForChurch($churchId);

        }

        if ($classId <= 0) {

            sendJSON(['success' => false, 'message' => 'لا توجد فصول في الكنيسة — أضف فصلاً أولاً']);

            return;

        }



        $snapshot = json_decode($req['student_snapshot'], true);

        if (!is_array($snapshot) || empty($snapshot['name'])) {

            sendJSON(['success' => false, 'message' => 'بيانات الطفل غير صالحة']);

            return;

        }



        $className = '';

        $hasCustom = $conn->prepare("SELECT arabic_name FROM church_classes WHERE id = ? AND church_id = ? AND is_active = 1");

        $hasCustom->bind_param("ii", $classId, $churchId);

        $hasCustom->execute();

        if ($cr = $hasCustom->get_result()->fetch_assoc()) {

            $className = $cr['arabic_name'];

        } else {

            $g = $conn->prepare("SELECT arabic_name FROM classes WHERE id = ?");

            $g->bind_param("i", $classId);

            $g->execute();

            if ($gr = $g->get_result()->fetch_assoc()) {

                $className = $gr['arabic_name'];

            }

        }



        $name = sanitize($snapshot['name']);

        $gender = in_array($snapshot['gender'] ?? '', ['male', 'female'], true) ? $snapshot['gender'] : 'male';

        $phone = sanitize($snapshot['phone'] ?? '');

        $address = sanitize($snapshot['address'] ?? '');

        $emergency = sanitize($snapshot['emergency_phone'] ?? '');

        $medical = sanitize($snapshot['medical_notes'] ?? '');

        $birthday = !empty($snapshot['birthday']) ? $snapshot['birthday'] : null;

        $customInfo = !empty($snapshot['custom_info']) ? $snapshot['custom_info'] : null;

        $imageUrl = $snapshot['image_url'] ?? null;

        $coupons = intval($snapshot['coupons'] ?? 0);

        $attC = intval($snapshot['attendance_coupons'] ?? 0);

        $comC = intval($snapshot['commitment_coupons'] ?? 0);

        $taskC = intval($snapshot['task_coupons'] ?? 0);



        $conn->begin_transaction();

        $ins = $conn->prepare("

            INSERT INTO students

                (church_id, name, gender, class_id, class, address, phone, emergency_phone,

                 medical_notes, birthday, coupons, attendance_coupons, commitment_coupons,

                 task_coupons, image_url, custom_info, enrollment_status)

            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')

        ");

        $ins->bind_param(

            "ississssssiiiiss",

            $churchId,

            $name,

            $gender,

            $classId,

            $className,

            $address,

            $phone,

            $emergency,

            $medical,

            $birthday,

            $coupons,

            $attC,

            $comC,

            $taskC,

            $imageUrl,

            $customInfo

        );

        if (!$ins->execute()) {

            $conn->rollback();

            sendJSON(['success' => false, 'message' => 'فشل إضافة الطفل: ' . $ins->error]);

            return;

        }

        $newStudentId = (int) $conn->insert_id;



        $upd = $conn->prepare("

            UPDATE student_transfer_requests

            SET status = 'accepted', target_class_id = ?, accepted_student_id = ?, updated_at = NOW()

            WHERE id = ?

        ");

        $upd->bind_param("iii", $classId, $newStudentId, $requestId);

        $upd->execute();



        $conn->commit();

        writeAuditLog('graduate_transfer_accept', 'students', $newStudentId, $name, null, null, 'class:' . $className);

        sendJSON([

            'success' => true,

            'message' => 'تم قبول الطفل وإضافته إلى ' . ($className ?: 'الفصل المحدد'),

            'student_id' => $newStudentId,

        ]);

    } catch (Exception $e) {

        if (isset($conn))

            $conn->rollback();

        error_log('respondGraduateTransfer: ' . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في معالجة الطلب']);

    }

}



// ===== CHURCH SETTINGS (attendance day + combined class + more) =====



/**

 * Ensure the church_settings table exists with all needed columns.

 */

function ensureChurchSettingsTable($conn)

{

    $conn->query("

        CREATE TABLE IF NOT EXISTS `church_settings` (

            `id`                     INT AUTO_INCREMENT PRIMARY KEY,

            `church_id`              INT NOT NULL UNIQUE,

            `attendance_day`         TINYINT NOT NULL DEFAULT 5

                                     COMMENT '1=Mon 2=Tue 3=Wed 4=Thu 5=Fri 6=Sat 7=Sun',

            `uncle_class_navigation` VARCHAR(10) NOT NULL DEFAULT 'all'

                                     COMMENT 'all | own',

            `combined_class_groups`  TEXT DEFAULT NULL

                                     COMMENT 'JSON array of {label, classes[]} groups',

            `custom_field`           TEXT DEFAULT NULL

                                     COMMENT 'JSON {name,icon} — one custom info field per church',

            `view_mode`              VARCHAR(10) NOT NULL DEFAULT 'classes'

                                     COMMENT 'classes | all | both',

            `updated_at`             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci

    ");

    // Safe migrations for existing installs

    $conn->query("ALTER TABLE church_settings ADD COLUMN IF NOT EXISTS

        `custom_field` TEXT DEFAULT NULL

        COMMENT 'JSON {name,icon} — one custom info field per church'");

    $conn->query("ALTER TABLE church_settings ADD COLUMN IF NOT EXISTS

        `view_mode` VARCHAR(10) NOT NULL DEFAULT 'classes'

        COMMENT 'classes | all | both'");

    ensureChurchSettingsAutoGradeColumns($conn);

}



function getChurchSettings()

{

    try {

        $churchId = getChurchId();

        if (!$churchId) {

            sendJSON(['success' => true, 'settings' => getDefaultChurchSettings()]);

            return;

        }

        $conn = getDBConnection();

        ensureChurchSettingsTable($conn);



        $stmt = $conn->prepare("SELECT * FROM church_settings WHERE church_id = ?");

        $stmt->bind_param("i", $churchId);

        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();



        if (!$row) {

            // Auto-create default row

            $conn->prepare("INSERT IGNORE INTO church_settings (church_id) VALUES (?)")

                ->bind_param("i", $churchId);

            $conn->query("INSERT IGNORE INTO church_settings (church_id) VALUES ($churchId)");

            $row = ['church_id' => $churchId] + getDefaultChurchSettings();

        }



        $rawCf = !empty($row['custom_field']) ? json_decode($row['custom_field'], true) : null;

        // Normalize: always return array of field objects or null

        $customFields = null;

        if (is_array($rawCf)) {

            if (isset($rawCf[0]) && is_array($rawCf[0])) {

                $customFields = $rawCf; // already an array

            } elseif (!empty($rawCf['name'])) {

                $customFields = [$rawCf]; // wrap single object in array

            }

        }



        $settings = [

            'attendance_day' => (int) ($row['attendance_day'] ?? 5),

            'uncle_class_navigation' => $row['uncle_class_navigation'] ?? 'all',

            'combined_class_groups' => $row['combined_class_groups']

                ? json_decode($row['combined_class_groups'], true) ?? []

                : [],

            'custom_field' => $customFields,

            'custom_fields' => $customFields, // alias for clarity

            'view_mode' => $row['view_mode'] ?? 'classes',

            'auto_kids_approval' => false, // will be overridden below

            'auto_grade_month' => isset($row['auto_grade_month']) ? (int) $row['auto_grade_month'] : null,

            'auto_grade_day' => isset($row['auto_grade_day']) ? (int) $row['auto_grade_day'] : null,

            'last_auto_grade_year' => isset($row['last_auto_grade_year']) ? (int) $row['last_auto_grade_year'] : null,

        ];



        // Load auto_kids_approval from churches.settings JSON

        $csStmt = $conn->prepare("SELECT settings FROM churches WHERE id = ? LIMIT 1");

        $csStmt->bind_param("i", $churchId);

        $csStmt->execute();

        $csRow = $csStmt->get_result()->fetch_assoc();

        if ($csRow && !empty($csRow['settings'])) {

            $cs = json_decode($csRow['settings'], true);

            $settings['auto_kids_approval'] = !empty($cs['auto_kids_approval']);

        }



        sendJSON(['success' => true, 'settings' => $settings]);

    } catch (Exception $e) {

        error_log("getChurchSettings error: " . $e->getMessage());

        sendJSON(['success' => true, 'settings' => getDefaultChurchSettings()]);

    }

}



function getDefaultChurchSettings(): array

{

    return [

        'attendance_day' => 5,

        'uncle_class_navigation' => 'all',

        'combined_class_groups' => [],

        'custom_field' => null,

        'view_mode' => 'classes',

        'auto_grade_month' => null,

        'auto_grade_day' => null,

        'last_auto_grade_year' => null,

    ];

}



function saveChurchSettings()

{

    try {

        $churchId = getChurchId();

        if (!$churchId) {

            sendJSON(['success' => false, 'message' => 'معرف الكنيسة مطلوب']);

            return;

        }

        $conn = getDBConnection();

        ensureChurchSettingsTable($conn);



        $attendanceDay = intval($_POST['attendance_day'] ?? 5);

        $uncleClassNav = sanitize($_POST['uncle_class_navigation'] ?? 'all');

        $combinedGroupsRaw = $_POST['combined_class_groups'] ?? '[]';

        $customFieldRaw = $_POST['custom_field'] ?? '';

        $viewMode = sanitize($_POST['view_mode'] ?? 'classes');

        $autoKidsApproval = !empty($_POST['auto_kids_approval']) && $_POST['auto_kids_approval'] === '1';

        $hasAutoGradeFields = array_key_exists('auto_grade_month', $_POST)

            || array_key_exists('auto_grade_day', $_POST);

        $autoGradeMonth = null;

        $autoGradeDay = null;

        if ($hasAutoGradeFields) {

            $autoGradeMonthRaw = $_POST['auto_grade_month'] ?? '';

            $autoGradeDayRaw = $_POST['auto_grade_day'] ?? '';

            $autoGradeMonth = ($autoGradeMonthRaw === '' || $autoGradeMonthRaw === '0')

                ? null : max(1, min(12, intval($autoGradeMonthRaw)));

            $autoGradeDay = ($autoGradeDayRaw === '' || $autoGradeDayRaw === '0')

                ? null : max(1, min(31, intval($autoGradeDayRaw)));

        }



        // Validate

        if ($attendanceDay < 1 || $attendanceDay > 7)

            $attendanceDay = 5;

        if (!in_array($uncleClassNav, ['all', 'own']))

            $uncleClassNav = 'all';

        if (!in_array($viewMode, ['classes', 'all', 'both']))

            $viewMode = 'classes';



        $combinedGroups = json_decode($combinedGroupsRaw, true);

        if (!is_array($combinedGroups))

            $combinedGroups = [];

        $combinedGroupsJson = json_encode($combinedGroups, JSON_UNESCAPED_UNICODE);



        // Validate custom fields: supports either a single {name,icon} object OR an array of them

        $customFieldJson = null;

        if (!empty($customFieldRaw)) {

            $cf = json_decode($customFieldRaw, true);

            if (is_array($cf)) {

                // Array of fields: [{name,icon}, ...]

                if (isset($cf[0]) && is_array($cf[0])) {

                    $cleaned = [];

                    foreach ($cf as $i => $field) {

                        if (!empty($field['name'])) {

                            // Assign a stable key if not already set — based on index

                            $stableKey = !empty($field['key']) ? sanitize($field['key']) : ('field_' . $i);

                            $cleaned[] = [

                                'name' => mb_substr(sanitize($field['name']), 0, 50),

                                'icon' => sanitize($field['icon'] ?? 'fa-info-circle'),

                                'key' => $stableKey,

                            ];

                        }

                    }

                    if (!empty($cleaned)) {

                        $customFieldJson = json_encode($cleaned, JSON_UNESCAPED_UNICODE);

                    }

                    // Single field object: {name, icon}

                } elseif (!empty($cf['name'])) {

                    $stableKey = !empty($cf['key']) ? sanitize($cf['key']) : 'field_0';

                    $cleaned = [

                        'name' => mb_substr(sanitize($cf['name']), 0, 50),

                        'icon' => sanitize($cf['icon'] ?? 'fa-info-circle'),

                        'key' => $stableKey,

                    ];

                    $customFieldJson = json_encode([$cleaned], JSON_UNESCAPED_UNICODE);

                }

            }

        }



        if ($hasAutoGradeFields) {

            $stmt = $conn->prepare("

                INSERT INTO church_settings

                    (church_id, attendance_day, uncle_class_navigation, combined_class_groups, custom_field, view_mode,

                     auto_grade_month, auto_grade_day)

                VALUES (?, ?, ?, ?, ?, ?, ?, ?)

                ON DUPLICATE KEY UPDATE

                    attendance_day         = VALUES(attendance_day),

                    uncle_class_navigation = VALUES(uncle_class_navigation),

                    combined_class_groups  = VALUES(combined_class_groups),

                    custom_field           = VALUES(custom_field),

                    view_mode              = VALUES(view_mode),

                    auto_grade_month       = VALUES(auto_grade_month),

                    auto_grade_day         = VALUES(auto_grade_day),

                    updated_at             = NOW()

            ");

            $stmt->bind_param(

                "iissssii",

                $churchId,

                $attendanceDay,

                $uncleClassNav,

                $combinedGroupsJson,

                $customFieldJson,

                $viewMode,

                $autoGradeMonth,

                $autoGradeDay

            );

        } else {

            $stmt = $conn->prepare("

                INSERT INTO church_settings

                    (church_id, attendance_day, uncle_class_navigation, combined_class_groups, custom_field, view_mode)

                VALUES (?, ?, ?, ?, ?, ?)

                ON DUPLICATE KEY UPDATE

                    attendance_day         = VALUES(attendance_day),

                    uncle_class_navigation = VALUES(uncle_class_navigation),

                    combined_class_groups  = VALUES(combined_class_groups),

                    custom_field           = VALUES(custom_field),

                    view_mode              = VALUES(view_mode),

                    updated_at             = NOW()

            ");

            $stmt->bind_param(

                "iissss",

                $churchId,

                $attendanceDay,

                $uncleClassNav,

                $combinedGroupsJson,

                $customFieldJson,

                $viewMode

            );

        }



        if ($stmt->execute()) {

            if ($hasAutoGradeFields && ($autoGradeMonth === null || $autoGradeDay === null)) {

                $clr = $conn->prepare("

                    UPDATE church_settings

                    SET auto_grade_month = NULL, auto_grade_day = NULL

                    WHERE church_id = ?

                ");

                $clr->bind_param("i", $churchId);

                $clr->execute();

            }

            // Save auto_kids_approval in churches.settings JSON

            $csStmt2 = $conn->prepare("SELECT settings FROM churches WHERE id = ? LIMIT 1");

            $csStmt2->bind_param("i", $churchId);

            $csStmt2->execute();

            $csRow2 = $csStmt2->get_result()->fetch_assoc();

            $existingSettings = [];

            if ($csRow2 && !empty($csRow2['settings'])) {

                $existingSettings = json_decode($csRow2['settings'], true) ?? [];

            }

            $existingSettings['auto_kids_approval'] = $autoKidsApproval;

            $newSettingsJson = json_encode($existingSettings, JSON_UNESCAPED_UNICODE);

            $updStmt = $conn->prepare("UPDATE churches SET settings = ? WHERE id = ?");

            $updStmt->bind_param("si", $newSettingsJson, $churchId);

            $updStmt->execute();



            sendJSON(['success' => true, 'message' => 'تم حفظ الإعدادات بنجاح']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في حفظ الإعدادات: ' . $stmt->error]);

        }

    } catch (Exception $e) {

        error_log("saveChurchSettings error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في حفظ الإعدادات: ' . $e->getMessage()]);

    }

}



/**

 * Uncle dashboard: Get data for a combined class group.

 * POST: group_label (string) — the label of the combined group

 * The API looks up combined_class_groups from church_settings and returns

 * all students from all classes in the group as a single merged list.

 */

function getUncleClassView()

{

    try {

        $churchId = getChurchId();

        $groupLabel = sanitize($_POST['group_label'] ?? '');



        if (empty($groupLabel)) {

            sendJSON(['success' => false, 'message' => 'اسم المجموعة مطلوب']);

            return;

        }



        $conn = getDBConnection();

        ensureChurchSettingsTable($conn);



        // Load combined groups for this church

        $stmt = $conn->prepare("SELECT combined_class_groups FROM church_settings WHERE church_id = ?");

        $stmt->bind_param("i", $churchId);

        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();

        $groups = $row ? json_decode($row['combined_class_groups'] ?? '[]', true) : [];



        // Find the matching group

        $targetGroup = null;

        foreach ($groups as $g) {

            if (($g['label'] ?? '') === $groupLabel) {

                $targetGroup = $g;

                break;

            }

        }



        if (!$targetGroup || empty($targetGroup['classes'])) {

            sendJSON(['success' => false, 'message' => 'المجموعة غير موجودة أو فارغة']);

            return;

        }



        $classNames = array_map('trim', (array) $targetGroup['classes']);

        if (empty($classNames)) {

            sendJSON(['success' => false, 'message' => 'لا توجد فصول في هذه المجموعة']);

            return;

        }



        // Resolve class IDs for the given names (custom + global)

        $churchClasses = getClassesForChurch($churchId);

        $classIdMap = [];

        foreach ($churchClasses as $c) {

            $classIdMap[mb_strtolower($c['arabic_name'])] = $c['id'];

        }



        $classIds = [];

        foreach ($classNames as $cn) {

            $key = mb_strtolower($cn);

            if (isset($classIdMap[$key])) {

                $classIds[] = (int) $classIdMap[$key];

            }

        }



        if (empty($classIds)) {

            sendJSON(['success' => false, 'message' => 'لم يتم العثور على فصول مطابقة']);

            return;

        }



        $placeholders = implode(',', array_fill(0, count($classIds), '?'));

        $types = str_repeat('i', count($classIds) + 1); // +1 for church_id



        $sql = "

            SELECT 

                s.id, s.name, s.phone, s.birthday, s.coupons,

                s.attendance_coupons, s.commitment_coupons, s.image_url, s.class_id,

                COALESCE(cc.arabic_name, gc.arabic_name, s.class) AS class_name,

                COALESCE(cc.code, gc.code) AS class_code

            FROM students s

            LEFT JOIN church_classes cc ON s.class_id = cc.id AND cc.church_id = s.church_id AND cc.is_active = 1

            LEFT JOIN classes gc        ON s.class_id = gc.id

            WHERE s.church_id = ? AND s.class_id IN ($placeholders)

            ORDER BY COALESCE(cc.display_order, gc.display_order, 999), s.name

        ";



        $bindValues = array_merge([$churchId], $classIds);

        $stmt2 = $conn->prepare($sql);

        $stmt2->bind_param($types, ...$bindValues);

        $stmt2->execute();

        $result = $stmt2->get_result();



        $students = [];

        while ($r = $result->fetch_assoc()) {

            $studentData = [

                'الاسم' => $r['name'],

                'الفصل' => $r['class_name'] ?? '',

                '_classId' => (int) $r['class_id'],

                '_classCode' => $r['class_code'] ?? '',

                'رقم التليفون' => $r['phone'] ?? '',

                'عيد الميلاد' => formatDateFromDB($r['birthday']),

                'كوبونات' => (int) $r['coupons'],

                'كوبونات الحضور' => (int) $r['attendance_coupons'],

                'كوبونات الالتزام' => (int) $r['commitment_coupons'],

                'صورة' => $r['image_url'] ?? '',

                '_studentId' => (int) $r['id'],

                '_allAttendance' => [],

            ];



            // Load recent attendance

            $attStmt = $conn->prepare("

                SELECT attendance_date, status FROM attendance

                WHERE student_id = ?

                ORDER BY attendance_date DESC LIMIT 50

            ");

            $attStmt->bind_param("i", $r['id']);

            $attStmt->execute();

            $attResult = $attStmt->get_result();

            while ($att = $attResult->fetch_assoc()) {

                $d = formatDateFromDB($att['attendance_date']);

                $v = $att['status'] === 'present' ? 'ح' : 'غ';

                $studentData[$d] = $v;

                $studentData['_allAttendance'][$d] = $v;

            }



            $students[] = $studentData;

        }



        sendJSON([

            'success' => true,

            'students' => $students,

            'group_label' => $groupLabel,

            'class_names' => $classNames,

            'count' => count($students),

        ]);



    } catch (Exception $e) {

        error_log("getUncleClassView error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحميل بيانات المجموعة: ' . $e->getMessage()]);

    }

}

// ── Delete a single audit log entry (admin only) ─────────────

function deleteAuditLog()

{

    try {

        $churchId = getChurchId();

        $logId = intval($_POST['log_id'] ?? 0);

        if (!$logId) {

            sendJSON(['success' => false, 'message' => 'معرف السجل مطلوب']);

            return;

        }

        $conn = getDBConnection();

        $stmt = $conn->prepare("DELETE FROM audit_logs WHERE id = ? AND church_id = ?");

        $stmt->bind_param("ii", $logId, $churchId);

        if ($stmt->execute() && $stmt->affected_rows > 0) {

            sendJSON(['success' => true, 'message' => 'تم حذف السجل']);

        } else {

            sendJSON(['success' => false, 'message' => 'لم يتم العثور على السجل']);

        }

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);

    }

}



// ── Clear all audit logs for this church ─────────────────────

function clearAllAuditLogs()

{

    try {

        $churchId = getChurchId();

        $from = sanitize($_POST['date_from'] ?? '');

        $to = sanitize($_POST['date_to'] ?? '');

        $conn = getDBConnection();

        if ($from && $to) {

            $stmt = $conn->prepare("DELETE FROM audit_logs WHERE church_id = ? AND DATE(created_at) BETWEEN ? AND ?");

            $stmt->bind_param("iss", $churchId, $from, $to);

        } elseif ($from) {

            $stmt = $conn->prepare("DELETE FROM audit_logs WHERE church_id = ? AND DATE(created_at) >= ?");

            $stmt->bind_param("is", $churchId, $from);

        } else {

            $stmt = $conn->prepare("DELETE FROM audit_logs WHERE church_id = ?");

            $stmt->bind_param("i", $churchId);

        }

        $stmt->execute();

        $deleted = $stmt->affected_rows;

        sendJSON(['success' => true, 'message' => "تم حذف $deleted سجل", 'deleted' => $deleted]);

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);

    }

}



// ── Get activity logs for the currently logged-in uncle ───────

function getUncleActivityLogs()

{

    try {

        $conn = getDBConnection();

        $churchId = getChurchId();

        $limit = max(10, min(500, intval($_POST['limit'] ?? 100)));



        // Works for both uncle login and church-admin login

        $uncleId = intval($_SESSION['uncle_id'] ?? 0);



        // If no uncle_id in session, try to find the uncle record by church_id

        // (this covers church admins who are also listed as uncles)

        if (!$uncleId && $churchId) {

            // Return logs for this church filtered to known admin actions

            // (no specific uncle_id filter — show all for this church)

            $stmt = $conn->prepare("

                SELECT id, action, entity, entity_id, entity_name,

                       uncle_name, old_data, new_data, notes, ip_address, created_at

                FROM audit_logs

                WHERE church_id = ?

                ORDER BY created_at DESC

                LIMIT ?

            ");

            $stmt->bind_param("ii", $churchId, $limit);

            $stmt->execute();

            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            sendJSON(['success' => true, 'logs' => $rows, 'total' => count($rows)]);

            return;

        }



        if (!$uncleId) {

            sendJSON(['success' => false, 'message' => 'غير مصرح - يرجى تسجيل الدخول']);

            return;

        }



        $stmt = $conn->prepare("

            SELECT id, action, entity, entity_id, entity_name,

                   uncle_name, old_data, new_data, notes, ip_address, created_at

            FROM audit_logs

            WHERE uncle_id = ? AND church_id = ?

            ORDER BY created_at DESC

            LIMIT ?

        ");

        $stmt->bind_param("iii", $uncleId, $churchId, $limit);

        $stmt->execute();

        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        sendJSON(['success' => true, 'logs' => $rows, 'total' => count($rows)]);



    } catch (Exception $e) {

        error_log("getUncleActivityLogs error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحميل السجل: ' . $e->getMessage()]);

    }

}



// ═══════════════════════════════════════════════════════════════

//  CHURCH REGISTRATION KEY SYSTEM

// ═══════════════════════════════════════════════════════════════



function ensureRegKeyTable($conn)

{

    $conn->query("

        CREATE TABLE IF NOT EXISTS church_reg_keys (

            id             INT AUTO_INCREMENT PRIMARY KEY,

            reg_key        VARCHAR(64) NOT NULL UNIQUE,

            label          VARCHAR(255) DEFAULT '',

            created_by     INT DEFAULT 0,

            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,

            used_at        DATETIME DEFAULT NULL,

            used_by_church INT DEFAULT NULL,

            is_revoked     TINYINT(1) DEFAULT 0,

            notes          TEXT DEFAULT NULL

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4

    ");

}



// DEV ONLY — generate a one-time church registration link

function generateChurchRegKey()

{

    $role = strtolower($_SESSION['uncle_role'] ?? $_SESSION['role'] ?? '');

    if (!in_array($role, ['developer', 'dev', 'admin', 'administrator'])) {

        sendJSON(['success' => false, 'message' => 'غير مصرح - للمطورين فقط']);

        return;

    }

    $label = sanitize($_POST['label'] ?? '');

    $createdBy = intval($_SESSION['uncle_id'] ?? $_SESSION['user_id'] ?? 0);

    $conn = getDBConnection();

    ensureRegKeyTable($conn);

    $key = bin2hex(random_bytes(24));

    $stmt = $conn->prepare("INSERT INTO church_reg_keys (reg_key, label, created_by) VALUES (?, ?, ?)");

    $stmt->bind_param("ssi", $key, $label, $createdBy);

    if ($stmt->execute()) {

        $proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        $base = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

        $regUrl = $base . '/church-register.html?key=' . $key;

        sendJSON(['success' => true, 'key' => $key, 'reg_url' => $regUrl, 'message' => 'تم توليد الرابط بنجاح']);

    } else {

        sendJSON(['success' => false, 'message' => 'فشل في توليد المفتاح: ' . $conn->error]);

    }

}



// DEV ONLY — list all generated keys

function listChurchRegKeys()

{

    $role = strtolower($_SESSION['uncle_role'] ?? $_SESSION['role'] ?? '');

    if (!in_array($role, ['developer', 'dev', 'admin', 'administrator'])) {

        sendJSON(['success' => false, 'message' => 'غير مصرح']);

        return;

    }

    $conn = getDBConnection();

    ensureRegKeyTable($conn);

    $result = $conn->query("

        SELECT k.*, c.church_name AS church_name

        FROM church_reg_keys k

        LEFT JOIN churches c ON c.id = k.used_by_church

        ORDER BY k.created_at DESC

    ");



    if (!$result) {

        sendJSON(['success' => false, 'message' => 'حدث خطأ في قاعدة البيانات: ' . $conn->error]);

        return;

    }



    $keys = [];

    $proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

    $base = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

    while ($row = $result->fetch_assoc()) {

        $row['reg_url'] = $base . '/church-register.html?key=' . $row['reg_key'];

        $keys[] = $row;

    }

    sendJSON(['success' => true, 'keys' => $keys]);

}



// DEV ONLY — revoke (or restore) a key

function revokeChurchRegKey()

{

    $role = strtolower($_SESSION['uncle_role'] ?? $_SESSION['role'] ?? '');

    if (!in_array($role, ['developer', 'dev', 'admin', 'administrator'])) {

        sendJSON(['success' => false, 'message' => 'غير مصرح']);

        return;

    }

    $key = sanitize($_POST['key'] ?? '');

    $revoke = intval($_POST['revoke'] ?? 1);

    if (empty($key)) {

        sendJSON(['success' => false, 'message' => 'المفتاح مطلوب']);

        return;

    }

    $conn = getDBConnection();

    ensureRegKeyTable($conn);

    $stmt = $conn->prepare("UPDATE church_reg_keys SET is_revoked = ? WHERE reg_key = ?");

    $stmt->bind_param("is", $revoke, $key);

    if ($stmt->execute() && $stmt->affected_rows > 0) {

        sendJSON(['success' => true, 'message' => $revoke ? 'تم إلغاء الرابط' : 'تم استعادة الرابط']);

    } else {

        sendJSON(['success' => false, 'message' => 'المفتاح غير موجود']);

    }

}



// PUBLIC — validate a key before showing the registration form

function validateChurchRegKey()

{

    $key = sanitize($_POST['key'] ?? '');

    if (empty($key)) {

        sendJSON(['success' => true, 'valid' => false, 'reason' => 'missing']);

        return;

    }

    $conn = getDBConnection();

    ensureRegKeyTable($conn);

    $stmt = $conn->prepare("SELECT id, is_revoked, used_at FROM church_reg_keys WHERE reg_key = ? LIMIT 1");

    $stmt->bind_param("s", $key);

    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {

        sendJSON(['success' => true, 'valid' => false, 'reason' => 'not_found']);

        return;

    }

    if ($row['is_revoked']) {

        sendJSON(['success' => true, 'valid' => false, 'reason' => 'revoked']);

        return;

    }

    if ($row['used_at']) {

        sendJSON(['success' => true, 'valid' => false, 'reason' => 'already_used']);

        return;

    }

    sendJSON(['success' => true, 'valid' => true]);

}



// PUBLIC — create church + admin uncle atomically, then mark key as used

function createChurchWithAdmin()

{

    $key = sanitize($_POST['reg_key'] ?? '');

    if (empty($key)) {

        sendJSON(['success' => false, 'message' => 'مفتاح التسجيل مطلوب']);

        return;

    }

    $conn = getDBConnection();

    ensureRegKeyTable($conn);

    $conn->begin_transaction();

    try {

        // Lock & validate

        $stmt = $conn->prepare("SELECT id, is_revoked, used_at FROM church_reg_keys WHERE reg_key = ? LIMIT 1 FOR UPDATE");

        $stmt->bind_param("s", $key);

        $stmt->execute();

        $keyRow = $stmt->get_result()->fetch_assoc();

        if (!$keyRow)

            throw new Exception('مفتاح التسجيل غير صالح');

        if ($keyRow['is_revoked'])

            throw new Exception('تم إلغاء هذا الرابط');

        if ($keyRow['used_at'])

            throw new Exception('تم استخدام هذا الرابط مسبقاً');



        // Inputs

        $churchName = sanitize($_POST['church_name'] ?? '');

        $churchCity = sanitize($_POST['church_city'] ?? '');

        $churchEmail = sanitize($_POST['church_email'] ?? '');

        $churchPhone = sanitize($_POST['church_phone'] ?? '');

        $churchType = in_array($_POST['church_type'] ?? '', ['kids', 'youth']) ? $_POST['church_type'] : 'kids';

        $adminName = sanitize($_POST['admin_name'] ?? '');

        $adminPhone = sanitize($_POST['admin_phone'] ?? '');

        $adminPassword = $_POST['admin_password'] ?? '';

        $adminEmail = sanitize($_POST['admin_email'] ?? '');

        $adminClass = sanitize($_POST['admin_class'] ?? 'الكل');



        if (empty($churchName))

            throw new Exception('اسم الكنيسة مطلوب');

        if (empty($adminName))

            throw new Exception('اسم المسؤول مطلوب');

        if (empty($adminPhone))

            throw new Exception('رقم تليفون المسؤول مطلوب');

        if (strlen($adminPassword) < 6)

            throw new Exception('كلمة المرور يجب أن تكون 6 أحرف على الأقل');



        // Check phone not already used

        $chk = $conn->prepare("SELECT id FROM uncles WHERE phone = ? LIMIT 1");

        $chk->bind_param("s", $adminPhone);

        $chk->execute();

        if ($chk->get_result()->fetch_assoc())

            throw new Exception('رقم التليفون مستخدم بالفعل في كنيسة أخرى');



        // Generate unique church code from name

        $baseCode = preg_replace('/[^a-z0-9]/i', '', str_replace(' ', '_', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $churchName))));

        $churchCode = $baseCode ?: 'church' . time();

        $codeCheck = $conn->prepare("SELECT id FROM churches WHERE church_code = ? LIMIT 1");

        $codeCheck->bind_param("s", $churchCode);

        $codeCheck->execute();

        if ($codeCheck->get_result()->fetch_assoc()) {

            $churchCode .= '_' . rand(100, 999);

        }



        // Create church — password for direct church login (same hashing as addChurch)

        $hashedChurchPw = password_hash($adminPassword, PASSWORD_DEFAULT);

        $insChurch = $conn->prepare("

            INSERT INTO churches (church_name, church_code, admin_email, password, church_type, created_at)

            VALUES (?, ?, ?, ?, ?, NOW())

        ");

        $insChurch->bind_param("sssss", $churchName, $churchCode, $churchEmail, $hashedChurchPw, $churchType);

        if (!$insChurch->execute())

            throw new Exception('فشل إنشاء الكنيسة: ' . $conn->error);

        $newChurchId = $conn->insert_id;



        // Create admin uncle

        $hashedUnclePw = password_hash($adminPassword, PASSWORD_DEFAULT);

        $insUncle = $conn->prepare("

            INSERT INTO uncles (church_id, name, phone, password, email, class, role, is_active, created_at)

            VALUES (?, ?, ?, ?, ?, ?, 'admin', 1, NOW())

        ");

        $insUncle->bind_param("isssss", $newChurchId, $adminName, $adminPhone, $hashedUnclePw, $adminEmail, $adminClass);

        if (!$insUncle->execute())

            throw new Exception('فشل إنشاء حساب المسؤول: ' . $conn->error);



        // Mark key used

        $useStmt = $conn->prepare("UPDATE church_reg_keys SET used_at = NOW(), church_id = ? WHERE reg_key = ?");

        $useStmt->bind_param("is", $newChurchId, $key);

        $useStmt->execute();



        $conn->commit();



        // Notify developer about new church registration (fire-and-forget)

        _sendNewChurchNotification($churchName, $adminName, $adminPhone, $churchEmail, $churchType);



        sendJSON([

            'success' => true,

            'message' => 'تم إنشاء الكنيسة بنجاح',

            'church_id' => $newChurchId,

            'church_name' => $churchName,

        ]);

    } catch (Exception $e) {

        $conn->rollback();

        error_log("createChurchWithAdmin error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



// PUBLIC — get basic church info for kid-register page

function getPublicChurchInfo()

{

    $churchId = intval($_POST['church_id'] ?? $_GET['church_id'] ?? 0);

    if (!$churchId) {

        sendJSON(['success' => false, 'message' => 'church_id مطلوب']);

        return;

    }

    $conn = getDBConnection();

    $stmt = $conn->prepare("SELECT id, church_name AS name, church_type AS type FROM churches WHERE id = ? LIMIT 1");

    $stmt->bind_param("i", $churchId);

    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {

        sendJSON(['success' => false, 'message' => 'الكنيسة غير موجودة']);

        return;

    }

    sendJSON(['success' => true, 'church_id' => $row['id'], 'church_name' => $row['name'], 'church_type' => $row['type']]);

}



// PUBLIC — check registration status by phone (for kid-register page)

function checkRegistrationStatus()

{

    $phone = sanitize($_POST['phone'] ?? '');

    $churchId = intval($_POST['church_id'] ?? 0);

    if (empty($phone)) {

        sendJSON(['success' => false, 'message' => 'رقم التليفون مطلوب']);

        return;

    }

    $conn = getDBConnection();



    // Check pending_registrations table (primary table used by submitRegistrationRequest)

    $tableCheck = $conn->query("SHOW TABLES LIKE 'pending_registrations'");

    if (!$tableCheck || $tableCheck->num_rows === 0) {

        sendJSON(['success' => true, 'registrations' => []]);

        return;

    }



    if ($churchId) {

        $stmt = $conn->prepare("

            SELECT id, name, class, status, created_at

            FROM pending_registrations

            WHERE phone = ? AND church_id = ?

            ORDER BY created_at DESC

        ");

        $stmt->bind_param("si", $phone, $churchId);

    } else {

        $stmt = $conn->prepare("

            SELECT id, name, class, status, created_at

            FROM pending_registrations

            WHERE phone = ?

            ORDER BY created_at DESC

        ");

        $stmt->bind_param("s", $phone);

    }

    $stmt->execute();

    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    sendJSON(['success' => true, 'registrations' => $rows]);

}



// ══════════════════════════════════════════════════════════════

// UNCLE REGISTRATION (plain church code — no keys)

// ══════════════════════════════════════════════════════════════



// PUBLIC — register uncle using plain church code (no one-time key needed)

function registerUncleWithChurchCode()

{

    $churchCode = sanitize($_POST['church_code'] ?? '');

    $name = sanitize($_POST['name'] ?? '');

    $username = sanitize($_POST['username'] ?? '');

    $password = $_POST['password'] ?? '';

    $classes = $_POST['classes'] ?? '[]';

    if (!$churchCode || !$name || !$username || strlen($password) < 6) {

        sendJSON(['success' => false, 'message' => 'بيانات ناقصة أو كلمة المرور قصيرة جداً']);

        return;

    }

    $conn = getDBConnection();

    // Resolve church code

    $cstmt = $conn->prepare("SELECT id, church_name, admin_email FROM churches WHERE church_code = ? LIMIT 1");

    $cstmt->bind_param("s", $churchCode);

    $cstmt->execute();

    $church = $cstmt->get_result()->fetch_assoc();

    if (!$church) {

        sendJSON(['success' => false, 'message' => 'الكنيسة غير موجودة']);

        return;

    }

    $churchId = $church['id'];

    // Check username unique

    $uchk = $conn->prepare("SELECT id FROM uncles WHERE username = ? LIMIT 1");

    $uchk->bind_param("s", $username);

    $uchk->execute();

    if ($uchk->get_result()->fetch_assoc()) {

        sendJSON(['success' => false, 'message' => 'اسم المستخدم مستخدم بالفعل']);

        return;

    }

    $hash = hash('sha256', $password);

    $role = 'uncle';

    $stmt = $conn->prepare("INSERT INTO uncles (church_id, name, username, password_hash, role) VALUES (?,?,?,?,?)");

    $stmt->bind_param("issss", $churchId, $name, $username, $hash, $role);

    if ($stmt->execute()) {

        $uncleId = $conn->insert_id;

        $classArr = json_decode($classes, true);

        if (!empty($classArr) && is_array($classArr)) {

            saveUncleClasses($uncleId, $churchId, $classArr);

        }

        _sendUncleRegistrationEmail($church['admin_email'] ?? '', $church['church_name'] ?? '', $name, $username);

        sendJSON(['success' => true, 'message' => 'تم التسجيل بنجاح']);

    } else {

        sendJSON(['success' => false, 'message' => 'فشل في إنشاء الحساب: ' . $conn->error]);

    }

}



// Internal — notify DEVELOPER when a new church registers via the registration link

function _sendNewChurchNotification($churchName, $adminName, $adminPhone, $churchEmail, $churchType)

{

    $appsScriptUrl = 'https://script.google.com/macros/s/AKfycbxsDA0veJTA3C_2Bw47coffOagRigWwaZnyxWuGb_gSVUCWM958V1bUcaZDwfIHVZ7b1g/exec';

    $payload = http_build_query([

        'action' => 'newChurchNotify',

        'church_name' => $churchName,

        'admin_name' => $adminName,

        'admin_phone' => $adminPhone,

        'church_email' => $churchEmail,

        'church_type' => $churchType,

        'timestamp' => date('Y-m-d H:i:s'),

    ]);

    @file_get_contents($appsScriptUrl . '?' . $payload);

}



// Internal — notify church admin by email via AppScript when uncle registers

function _sendUncleRegistrationEmail($adminEmail, $churchName, $uncleName, $username)

{

    $appsScriptUrl = 'https://script.google.com/macros/s/AKfycbxsDA0veJTA3C_2Bw47coffOagRigWwaZnyxWuGb_gSVUCWM958V1bUcaZDwfIHVZ7b1g/exec';

    if (empty($adminEmail))

        return;

    $payload = http_build_query([

        'action' => 'registerUncleNotify',

        'church_name' => $churchName,

        'church_email' => $adminEmail,

        'uncle_name' => $uncleName,

        'username' => $username,

        'timestamp' => date('Y-m-d H:i:s'),

    ]);

    @file_get_contents($appsScriptUrl . '?' . $payload);

}



// ══════════════════════════════════════════════════════════════════

// TASKS FUNCTIONS

// ══════════════════════════════════════════════════════════════════



// ─── getTasks ──────────────────────────────────────────────────

function getTasks()

{

    try {

        $conn = getDBConnection();

        $churchId = getChurchId();

        $uncleId = $_SESSION['uncle_id'] ?? null;

        $uncleRole = strtolower($_SESSION['uncle_role'] ?? '');

        $className = sanitize($_POST['class_name'] ?? '');



        $isAdmin = in_array($uncleRole, ['admin', 'developer', 'church']);



        $taskCols = [];

        $colRes = $conn->query("SHOW COLUMNS FROM tasks");

        while ($cr = $colRes->fetch_assoc())

            $taskCols[] = $cr['Field'];

        $hasNoDeadline = in_array('no_deadline', $taskCols);

        $hasClassIds = in_array('class_ids', $taskCols);



        $sql = "

            SELECT

                t.id, t.uncle_id, t.class_id, t.title, t.description,

                t.start_date, t.end_date, t.time_limit, t.timer_behavior,

                t.total_degree, t.max_coupons, t.coupon_matrix,

                t.status, t.assign_to, t.specific_ids,

                t.shuffle, t.show_result, t.show_answers, t.allow_review" . ($hasNoDeadline ? ", t.no_deadline" : "") . ($hasClassIds ? ", t.class_ids" : "") . ",

                t.created_at,

                CASE

                    WHEN t.class_id = 0 THEN 'كل الفصول'

                    ELSE COALESCE(cc.arabic_name, '')

                END AS class_name,

                u.name AS uncle_name

            FROM tasks t

            LEFT JOIN church_classes cc ON cc.id = t.class_id AND cc.church_id = t.church_id

            LEFT JOIN uncles u ON u.id = t.uncle_id

            WHERE t.church_id = ?

        ";

        $params = [$churchId];

        $types = 'i';



        if (!$isAdmin && $uncleId) {

            $sql .= " AND t.uncle_id = ?";

            $params[] = (int) $uncleId;

            $types .= 'i';

        }

        if ($className) {

            if ($hasClassIds) {

                $cStmt = $conn->prepare("SELECT id FROM church_classes WHERE church_id = ? AND arabic_name = ? LIMIT 1");

                $cStmt->bind_param('is', $churchId, $className);

                $cStmt->execute();

                $cId = (int)($cStmt->get_result()->fetch_assoc()['id'] ?? 0);

                

                $sql .= " AND (cc.arabic_name = ? OR t.class_id = 0 OR FIND_IN_SET(?, t.class_ids))";

                $params[] = $className;

                $params[] = $cId;

                $types .= 'si';

            } else {

                $sql .= " AND (cc.arabic_name = ? OR t.class_id = 0)";

                $params[] = $className;

                $types .= 's';

            }

        }



        $sql .= " ORDER BY t.created_at DESC";



        $stmt = $conn->prepare($sql);

        $stmt->bind_param($types, ...$params);

        $stmt->execute();

        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);



        $taskIds = array_column($rows, 'id');

        $questions = [];

        $submissions = [];



        if ($taskIds) {

            $inList = implode(',', array_map('intval', $taskIds));



            $qRes = $conn->query("

                SELECT id, task_id, question_type, question_text, options, correct_index, degree, sort_order, image_url

                FROM task_questions WHERE task_id IN ($inList)

                ORDER BY task_id, sort_order

            ");

            while ($r = $qRes->fetch_assoc()) {

                $questions[$r['task_id']][] = $r;

            }



            $sRes = $conn->query("

                SELECT ts.task_id, ts.student_id, ts.score, ts.coupons_awarded, ts.submitted_at,

                       ts.answers, ts.open_scores, ts.correction_notes, ts.is_graded,

                       s.name AS student_name

                FROM task_submissions ts

                LEFT JOIN students s ON s.id = ts.student_id

                WHERE ts.task_id IN ($inList)

                ORDER BY ts.submitted_at DESC

            ");

            while ($r = $sRes->fetch_assoc()) {

                $submissions[$r['task_id']][] = $r;

            }

        }



        foreach ($rows as &$t) {

            $t['no_deadline'] = isset($t['no_deadline']) ? (int) $t['no_deadline'] : 0;

            $t['questions'] = $questions[$t['id']] ?? [];

            $t['submissions'] = $submissions[$t['id']] ?? [];

        }

        unset($t);



        sendJSON(['success' => true, 'tasks' => $rows]);

    } catch (Exception $e) {

        error_log("getTasks error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



// ─── getTaskDetail ─────────────────────────────────────────────

function getTaskDetail()

{

    try {

        $conn = getDBConnection();

        $churchId = getChurchId();

        $taskId = (int) ($_POST['task_id'] ?? 0);



        if (!$taskId) {

            sendJSON(['success' => false, 'message' => 'task_id مطلوب']);

            return;

        }



        $stmt = $conn->prepare("

            SELECT t.*,

                   CASE

                       WHEN t.class_id = 0 THEN 'كل الفصول'

                       ELSE COALESCE(cc.arabic_name,'')

                   END AS class_name,

                   u.name AS uncle_name

            FROM tasks t

            LEFT JOIN church_classes cc ON cc.id = t.class_id AND cc.church_id = t.church_id

            LEFT JOIN uncles u ON u.id = t.uncle_id

            WHERE t.id = ? AND t.church_id = ?

        ");

        $stmt->bind_param('ii', $taskId, $churchId);

        $stmt->execute();

        $task = $stmt->get_result()->fetch_assoc();

        if ($task)

            $task['no_deadline'] = isset($task['no_deadline']) ? (int) $task['no_deadline'] : 0;

        if (!$task) {

            sendJSON(['success' => false, 'message' => 'المهمة غير موجودة']);

            return;

        }



        $qStmt = $conn->prepare("

            SELECT id, task_id, question_type, question_text, options, correct_index, degree, sort_order, image_url

            FROM task_questions WHERE task_id = ? ORDER BY sort_order

        ");

        $qStmt->bind_param('i', $taskId);

        $qStmt->execute();

        $task['questions'] = $qStmt->get_result()->fetch_all(MYSQLI_ASSOC);



        $sStmt = $conn->prepare("

            SELECT ts.*, s.name AS student_name

            FROM task_submissions ts

            LEFT JOIN students s ON s.id = ts.student_id

            WHERE ts.task_id = ?

            ORDER BY ts.submitted_at DESC

        ");

        $sStmt->bind_param('i', $taskId);

        $sStmt->execute();

        $task['submissions'] = $sStmt->get_result()->fetch_all(MYSQLI_ASSOC);



        sendJSON(['success' => true, 'task' => $task]);

    } catch (Exception $e) {

        error_log("getTaskDetail error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



// ─── createTask ────────────────────────────────────────────────

function createTask()

{

    try {

        $conn = getDBConnection();

        $churchId = getChurchId();

        $uncleId = (int) ($_SESSION['uncle_id'] ?? 0);



        $title = sanitize($_POST['title'] ?? '');

        $description = sanitize($_POST['description'] ?? '');

        $className = sanitize($_POST['class_name'] ?? '');

        $classId = (int) ($_POST['class_id'] ?? 0);

        $assignTo = in_array($_POST['assign_to'] ?? 'all', ['all', 'specific']) ? $_POST['assign_to'] : 'all';

        $specificIds = $_POST['specific_ids'] ?? '[]';

        $startDate = sanitize($_POST['start_date'] ?? '');

        $endDateRaw = trim($_POST['end_date'] ?? '');

        $noDeadline = (int) ($_POST['no_deadline'] ?? 0) ? 1 : 0;

        $endDate = $noDeadline ? '9999-12-31 23:59:59' : ($endDateRaw !== '' ? sanitize($endDateRaw) : null);

        $timeLimit = !empty($_POST['time_limit']) ? (int) $_POST['time_limit'] : null;

        $timerBeh = in_array($_POST['timer_behavior'] ?? 'submit', ['submit', 'lock']) ? $_POST['timer_behavior'] : 'submit';

        $totalDegree = (int) ($_POST['total_degree'] ?? 0);

        $maxCoupons = (int) ($_POST['max_coupons'] ?? 0);

        $couponMatrix = $_POST['coupon_matrix'] ?? '[]';

        $status = in_array($_POST['status'] ?? 'published', ['draft', 'published']) ? $_POST['status'] : 'published';

        $shuffle = (int) ($_POST['shuffle'] ?? 0);

        $showResult = (int) ($_POST['show_result'] ?? 1);

        $showAnswers = (int) ($_POST['show_answers'] ?? 0);

        $allowReview = (int) ($_POST['allow_review'] ?? 1);

        $questionsJson = $_POST['questions'] ?? '[]';



        if (!$title) {

            sendJSON(['success' => false, 'message' => 'العنوان مطلوب']);

            return;

        }

        if (!$startDate) {

            sendJSON(['success' => false, 'message' => 'تاريخ البداية مطلوب']);

            return;

        }

        if (!$endDate) {

            sendJSON(['success' => false, 'message' => 'الموعد النهائي مطلوب']);

            return;

        }



        if (!$classId && $className) {

            $cStmt = $conn->prepare("SELECT id FROM church_classes WHERE church_id=? AND arabic_name=? LIMIT 1");

            $cStmt->bind_param('is', $churchId, $className);

            $cStmt->execute();

            $cRow = $cStmt->get_result()->fetch_assoc();

            if ($cRow)

                $classId = (int) $cRow['id'];

        }



        $classIds = sanitize($_POST['class_ids'] ?? '');

        if (empty($classIds)) {

            $classIds = (string)$classId;

        }

        $conn->query("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS no_deadline TINYINT(1) NOT NULL DEFAULT 0 AFTER end_date");

        $conn->query("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS class_ids VARCHAR(255) NULL AFTER class_id");

        $conn->begin_transaction();



        $stmt = $conn->prepare("

            INSERT INTO tasks

                (church_id, uncle_id, class_id, class_ids, title, description,

                 start_date, end_date, no_deadline, time_limit, timer_behavior,

                 total_degree, max_coupons, coupon_matrix,

                 status, assign_to, specific_ids,

                 shuffle, show_result, show_answers, allow_review, created_at)

            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())

        ");

        $stmt->bind_param(

            'iiisssssiisiissssiiii',

            $churchId,

            $uncleId,

            $classId,

            $classIds,

            $title,

            $description,

            $startDate,

            $endDate,

            $noDeadline,

            $timeLimit,

            $timerBeh,

            $totalDegree,

            $maxCoupons,

            $couponMatrix,

            $status,

            $assignTo,

            $specificIds,

            $shuffle,

            $showResult,

            $showAnswers,

            $allowReview

        );

        $stmt->execute();

        $taskId = $conn->insert_id;



        $questions = json_decode($questionsJson, true) ?: [];

        _insertTaskQuestions($conn, $taskId, $questions);



        $conn->commit();



        if (function_exists('writeAuditLog')) {

            writeAuditLog('create', 'task', $taskId, $title);

        }



        sendJSON(['success' => true, 'task_id' => $taskId, 'message' => 'تم إنشاء المهمة']);

    } catch (Exception $e) {

        if (isset($conn))

            $conn->rollback();

        error_log("createTask error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



// ─── deleteSubmission ──────────────────────────────────────────

function deleteSubmission()

{

    try {

        $conn = getDBConnection();

        $churchId = getChurchId();

        $subId = (int) ($_POST['submission_id'] ?? 0);



        if (!$subId) {

            sendJSON(['success' => false, 'message' => 'submission_id مطلوب']);

            return;

        }



        // Load submission so we can reverse coupons

        $sel = $conn->prepare("

            SELECT ts.student_id, ts.coupons_awarded, ts.task_id, t.title

            FROM task_submissions ts

            JOIN tasks t ON t.id = ts.task_id

            WHERE ts.id = ? AND ts.church_id = ?

        ");

        $sel->bind_param('ii', $subId, $churchId);

        $sel->execute();

        $sub = $sel->get_result()->fetch_assoc();

        if (!$sub) {

            sendJSON(['success' => false, 'message' => 'السجل غير موجود أو غير مصرح']);

            return;

        }



        $conn->begin_transaction();



        // Reverse task_coupons ONLY — never touch total coupons

        $awarded = (int) $sub['coupons_awarded'];

        $uncleId = (int) ($_SESSION['uncle_id'] ?? 0);

        if ($awarded > 0) {

            $stuStmt = $conn->prepare("SELECT name, coupons, task_coupons, attendance_coupons, commitment_coupons FROM students WHERE id=? LIMIT 1");

            $stuStmt->bind_param('i', $sub['student_id']);

            $stuStmt->execute();

            $stu = $stuStmt->get_result()->fetch_assoc();

            if ($stu) {

                $newTask = max(0, (int) $stu['task_coupons'] - $awarded);

                $newTotal = $newTask + (int) $stu['attendance_coupons'] + (int) $stu['commitment_coupons'];

                $conn->query("UPDATE students SET task_coupons={$newTask}, coupons={$newTotal} WHERE id={$sub['student_id']}");

                // Log the reversal so the audit trail is complete

                $negAwarded = -$awarded;

                $reason = "حذف إجابة مهمة #{$sub['task_id']}: {$sub['title']}";

                $logStmt = $conn->prepare("INSERT INTO coupon_logs (student_id, uncle_id, old_count, new_count, change_amount, change_type, reason) VALUES (?,?,?,?,?,'task',?)");

                $logStmt->bind_param('iiiiss', $sub['student_id'], $uncleId, $stu['task_coupons'], $newTask, $negAwarded, $reason);

                $logStmt->execute();



                // ► AUDIT

                auditCouponChange($sub['student_id'], $stu['name'] ?? '', (int) $stu['coupons'], $newTotal, $reason);

            }

        }



        // Delete the submission (also delete exam_start record so student can retake)

        $conn->query("DELETE FROM task_submissions WHERE id=$subId");

        $conn->query("DELETE FROM exam_starts WHERE task_id={$sub['task_id']} AND student_id={$sub['student_id']}");



        $conn->commit();



        if (function_exists('writeAuditLog')) {

            writeAuditLog('delete', 'submission', $subId, "إجابة على: {$sub['title']}");

        }



        sendJSON(['success' => true, 'message' => 'تم حذف الإجابة وعكس الكوبونات', 'coupons_reversed' => $awarded]);

    } catch (Exception $e) {

        if (isset($conn))

            $conn->rollback();

        error_log("deleteSubmission error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



// ─── updateTask ────────────────────────────────────────────────

function updateTask()

{

    try {

        $conn = getDBConnection();

        $churchId = getChurchId();

        $uncleId = (int) ($_SESSION['uncle_id'] ?? 0);

        $uncleRole = strtolower($_SESSION['uncle_role'] ?? '');

        $taskId = (int) ($_POST['task_id'] ?? 0);



        if (!$taskId) {

            sendJSON(['success' => false, 'message' => 'task_id مطلوب']);

            return;

        }



        if (!in_array($uncleRole, ['admin', 'developer', 'church'])) {

            $chk = $conn->prepare("SELECT uncle_id FROM tasks WHERE id=? AND church_id=?");

            $chk->bind_param('ii', $taskId, $churchId);

            $chk->execute();

            $r = $chk->get_result()->fetch_assoc();

            if (!$r || (int) $r['uncle_id'] !== $uncleId) {

                sendJSON(['success' => false, 'message' => 'غير مصرح بتعديل هذه المهمة']);

                return;

            }

        }



        $title = sanitize($_POST['title'] ?? '');

        $description = sanitize($_POST['description'] ?? '');

        $className = sanitize($_POST['class_name'] ?? '');

        $classId = (int) ($_POST['class_id'] ?? 0);

        $assignTo = in_array($_POST['assign_to'] ?? 'all', ['all', 'specific']) ? $_POST['assign_to'] : 'all';

        $specificIds = $_POST['specific_ids'] ?? '[]';

        $startDate = sanitize($_POST['start_date'] ?? '');

        $endDateRaw = trim($_POST['end_date'] ?? '');

        $noDeadline = (int) ($_POST['no_deadline'] ?? 0) ? 1 : 0;

        $endDate = $noDeadline ? '9999-12-31 23:59:59' : sanitize($endDateRaw);

        $timeLimit = !empty($_POST['time_limit']) ? (int) $_POST['time_limit'] : null;

        $timerBeh = in_array($_POST['timer_behavior'] ?? 'submit', ['submit', 'lock']) ? $_POST['timer_behavior'] : 'submit';

        $totalDegree = (int) ($_POST['total_degree'] ?? 0);

        $maxCoupons = (int) ($_POST['max_coupons'] ?? 0);

        $couponMatrix = $_POST['coupon_matrix'] ?? '[]';

        $status = in_array($_POST['status'] ?? 'published', ['draft', 'published', 'archived']) ? $_POST['status'] : 'published';

        $shuffle = (int) ($_POST['shuffle'] ?? 0);

        $showResult = (int) ($_POST['show_result'] ?? 1);

        $allowReview = (int) ($_POST['allow_review'] ?? 1);

        $questionsJson = $_POST['questions'] ?? '[]';



        if (!$classId && $className) {

            $cStmt = $conn->prepare("SELECT id FROM church_classes WHERE church_id=? AND arabic_name=? LIMIT 1");

            $cStmt->bind_param('is', $churchId, $className);

            $cStmt->execute();

            $cRow = $cStmt->get_result()->fetch_assoc();

            if ($cRow)

                $classId = (int) $cRow['id'];

        }



        $classIds = sanitize($_POST['class_ids'] ?? '');

        if (empty($classIds)) {

            $classIds = (string)$classId;

        }

        $conn->query("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS no_deadline TINYINT(1) NOT NULL DEFAULT 0 AFTER end_date");

        $conn->query("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS class_ids VARCHAR(255) NULL AFTER class_id");

        $conn->begin_transaction();



        $stmt = $conn->prepare("

            UPDATE tasks SET

                class_id=?, class_ids=?, title=?, description=?,

                start_date=?, end_date=?, no_deadline=?, time_limit=?, timer_behavior=?,

                total_degree=?, max_coupons=?, coupon_matrix=?,

                status=?, assign_to=?, specific_ids=?,

                shuffle=?, show_result=?, allow_review=?,

                updated_at=NOW()

            WHERE id=? AND church_id=?

        ");

        $stmt->bind_param(

            'isssssiisiissssiiiii',

            $classId,

            $classIds,

            $title,

            $description,

            $startDate,

            $endDate,

            $noDeadline,

            $timeLimit,

            $timerBeh,

            $totalDegree,

            $maxCoupons,

            $couponMatrix,

            $status,

            $assignTo,

            $specificIds,

            $shuffle,

            $showResult,

            $allowReview,

            $taskId,

            $churchId

        );

        $stmt->execute();



        $delQ = $conn->prepare("DELETE FROM task_questions WHERE task_id=?");

        $delQ->bind_param('i', $taskId);

        $delQ->execute();



        $questions = json_decode($questionsJson, true) ?: [];

        _insertTaskQuestions($conn, $taskId, $questions);



        // ── After inserting new questions, recalculate MCQ/TF scores ──

        // Load the newly inserted questions

        $newQsStmt = $conn->prepare("SELECT id, question_type, correct_index, degree FROM task_questions WHERE task_id=?");

        $newQsStmt->bind_param('i', $taskId);

        $newQsStmt->execute();

        $newQs = $newQsStmt->get_result()->fetch_all(MYSQLI_ASSOC);



        // Load all submissions for this task

        $allSubsStmt = $conn->prepare("SELECT id, student_id, answers, score AS old_score, coupons_awarded AS old_coupons, open_scores FROM task_submissions WHERE task_id=?");

        $allSubsStmt->bind_param('i', $taskId);

        $allSubsStmt->execute();

        $allSubs = $allSubsStmt->get_result()->fetch_all(MYSQLI_ASSOC);



        $newMatrix = json_decode($couponMatrix, true) ?: [];



        foreach ($allSubs as $sub) {

            $answers = json_decode($sub['answers'] ?? '{}', true) ?: [];

            $openScores = json_decode($sub['open_scores'] ?? '{}', true) ?: [];



            $mcqScore = 0;

            $openScore = 0;

            foreach ($newQs as $q) {

                if ($q['question_type'] === 'open') {

                    $openScore += (int) ($openScores[$q['id']] ?? $openScores[(string) $q['id']] ?? 0);

                } else {

                    if ($q['correct_index'] === null)

                        continue;

                    $given = $answers[$q['id']] ?? $answers[(string) $q['id']] ?? null;

                    if ($given !== null && (int) $given === (int) $q['correct_index']) {

                        $mcqScore += (int) $q['degree'];

                    }

                }

            }

            $newScore = $mcqScore + $openScore;



            // Compute new coupons from matrix

            $pct = $totalDegree > 0 ? ($newScore / $totalDegree * 100) : 0;

            $newCoupons = 0;

            foreach ($newMatrix as $tier) {

                if ($pct >= (float) $tier['from'] && $pct <= (float) $tier['to']) {

                    $newCoupons = (int) $tier['val'];

                    break;

                }

            }



            $oldScore = (int) $sub['old_score'];

            $oldCoupons = (int) $sub['old_coupons'];

            $couponDiff = $newCoupons - $oldCoupons;



            // Update submission score and coupons

            $updSub = $conn->prepare("UPDATE task_submissions SET score=?, coupons_awarded=? WHERE id=?");

            $updSub->bind_param('iii', $newScore, $newCoupons, $sub['id']);

            $updSub->execute();



            // Apply coupon diff to student

            if ($couponDiff !== 0) {

                $stuQ2 = $conn->prepare("SELECT name, coupons, task_coupons, attendance_coupons, commitment_coupons FROM students WHERE id=? LIMIT 1");

                $stuQ2->bind_param('i', $sub['student_id']);

                $stuQ2->execute();

                $stu2 = $stuQ2->get_result()->fetch_assoc();

                if ($stu2) {

                    $newTask2 = max(0, (int) $stu2['task_coupons'] + $couponDiff);

                    $newTotal2 = $newTask2 + (int) $stu2['attendance_coupons'] + (int) $stu2['commitment_coupons'];

                    $conn->query("UPDATE students SET task_coupons={$newTask2}, coupons={$newTotal2} WHERE id={$sub['student_id']}");

                    // Log

                    $reason2 = "تحديث مهمة #{$taskId}: تعديل درجة {$oldScore}→{$newScore}";

                    $log2 = $conn->prepare("INSERT INTO coupon_logs (student_id, uncle_id, old_count, new_count, change_amount, change_type, reason) VALUES (?,?,?,?,?,'task',?)");

                    $log2->bind_param('iiiiss', $sub['student_id'], $uncleId, $stu2['task_coupons'], $newTask2, $couponDiff, $reason2);

                    $log2->execute();



                    // ► AUDIT

                    auditCouponChange($sub['student_id'], $stu2['name'] ?? '', (int) $stu2['coupons'], $newTotal2, $reason2);

                }

            }

        }

        // Send announcement to notify kids of the update
        if ($status === 'published') {
            $notifiedClass = '';
            $notifiedStudents = '';
            if ($assignTo === 'specific') {
                $specArray = json_decode($specificIds, true) ?: [];
                if (!empty($specArray)) {
                    $specIdsStr = implode(',', array_map('intval', $specArray));
                    $stuQ = $conn->query("SELECT name FROM students WHERE id IN ($specIdsStr) AND church_id = $churchId");
                    $names = [];
                    while ($sRow = $stuQ->fetch_assoc()) {
                        $names[] = $sRow['name'];
                    }
                    $notifiedStudents = implode(',', $names);
                }
            } else {
                if ($classId == 0) {
                    $notifiedClass = 'الجميع';
                } else {
                    $classIdsArray = array_filter(array_map('intval', explode(',', $classIds)));
                    if (!empty($classIdsArray)) {
                        $classIdsStr = implode(',', $classIdsArray);
                        $classQ = $conn->query("SELECT arabic_name FROM church_classes WHERE id IN ($classIdsStr) AND church_id = $churchId");
                        $classNames = [];
                        while ($cRow = $classQ->fetch_assoc()) {
                            $classNames[] = $cRow['arabic_name'];
                        }
                        $notifiedClass = implode(',', $classNames);
                    } else {
                        $notifiedClass = $className; // fallback
                    }
                }
            }

            // Check if there's any target class or student
            if ($notifiedClass !== '' || $notifiedStudents !== '') {
                $annText = "تم تعديل المهمة: " . $title . " وتحديث الكوبونات الخاصة بها.";
                $annStmt = $conn->prepare("INSERT INTO announcements (church_id, type, text, link, class, student_names, is_active, created_at) VALUES (?, 'task', ?, '', ?, ?, 1, NOW())");
                $annStmt->bind_param('isss', $churchId, $annText, $notifiedClass, $notifiedStudents);
                $annStmt->execute();
            }
        }

        $conn->commit();



        if (function_exists('writeAuditLog')) {

            writeAuditLog('update', 'task', $taskId, $title);

        }



        sendJSON(['success' => true, 'message' => 'تم تحديث المهمة وإعادة حساب نتائج الأطفال']);

    } catch (Exception $e) {

        if (isset($conn))

            $conn->rollback();

        error_log("updateTask error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



// ─── deleteTask ────────────────────────────────────────────────

function deleteTask()

{

    try {

        $conn = getDBConnection();

        $churchId = getChurchId();

        $uncleId = (int) ($_SESSION['uncle_id'] ?? 0);

        $uncleRole = strtolower($_SESSION['uncle_role'] ?? '');

        $taskId = (int) ($_POST['task_id'] ?? 0);



        if (!$taskId) {

            sendJSON(['success' => false, 'message' => 'task_id مطلوب']);

            return;

        }



        if (!in_array($uncleRole, ['admin', 'developer', 'church'])) {

            $chk = $conn->prepare("SELECT uncle_id FROM tasks WHERE id=? AND church_id=?");

            $chk->bind_param('ii', $taskId, $churchId);

            $chk->execute();

            $r = $chk->get_result()->fetch_assoc();

            if (!$r || (int) $r['uncle_id'] !== $uncleId) {

                sendJSON(['success' => false, 'message' => 'غير مصرح']);

                return;

            }

        }



        // reverse_coupons=1 -> withdraw coupons from kids, reverse_coupons=0 -> keep them

        $reverseCoupons = isset($_POST['reverse_coupons']) ? (int) $_POST['reverse_coupons'] : 1;



        $conn->begin_transaction();



        // Handle coupons for all submissions before deleting

        $totalReversed = 0;

        $subsStmt = $conn->prepare("SELECT student_id, coupons_awarded FROM task_submissions WHERE task_id=? AND coupons_awarded > 0");

        $subsStmt->bind_param('i', $taskId);

        $subsStmt->execute();

        $subsRows = $subsStmt->get_result()->fetch_all(MYSQLI_ASSOC);



        foreach ($subsRows as $subRow) {

            $awarded = (int) $subRow['coupons_awarded'];

            if ($awarded <= 0)

                continue;



            if ($reverseCoupons) {

                // Withdraw the coupons from the student

                $stuQ = $conn->prepare("SELECT name, coupons, task_coupons, attendance_coupons, commitment_coupons FROM students WHERE id=? LIMIT 1");

                $stuQ->bind_param('i', $subRow['student_id']);

                $stuQ->execute();

                $stu = $stuQ->get_result()->fetch_assoc();

                if ($stu) {

                    $newTask = max(0, (int) $stu['task_coupons'] - $awarded);

                    $newTotal = $newTask + (int) $stu['attendance_coupons'] + (int) $stu['commitment_coupons'];

                    $conn->query("UPDATE students SET task_coupons={$newTask}, coupons={$newTotal} WHERE id={$subRow['student_id']}");

                    $totalReversed += $awarded;

                    $reason = "حذف مهمة #{$taskId} (مع سحب الكوبونات)";

                    $negAwarded = -$awarded;

                    $log = $conn->prepare("INSERT INTO coupon_logs (student_id, uncle_id, old_count, new_count, change_amount, change_type, reason) VALUES (?,?,?,?,?,'task',?)");

                    $log->bind_param('iiiiss', $subRow['student_id'], $uncleId, $stu['task_coupons'], $newTask, $negAwarded, $reason);

                    $log->execute();



                    // ► AUDIT

                    auditCouponChange($subRow['student_id'], $stu['name'] ?? '', (int) $stu['coupons'], $newTotal, $reason);

                }

            } else {

                // Keep the coupons — just log that the task was removed but coupons were retained

                $stuQ = $conn->prepare("SELECT task_coupons FROM students WHERE id=? LIMIT 1");

                $stuQ->bind_param('i', $subRow['student_id']);

                $stuQ->execute();

                $stu = $stuQ->get_result()->fetch_assoc();

                if ($stu) {

                    $reason = "حذف مهمة #{$taskId} (الكوبونات محتفظ بها)";

                    $zero = 0;

                    $log = $conn->prepare("INSERT INTO coupon_logs (student_id, uncle_id, old_count, new_count, change_amount, change_type, reason) VALUES (?,?,?,?,?,'task',?)");

                    $log->bind_param('iiiiss', $subRow['student_id'], $uncleId, $stu['task_coupons'], $stu['task_coupons'], $zero, $reason);

                    $log->execute();

                }

            }

        }



        // Also delete exam_starts

        $conn->query("DELETE FROM exam_starts WHERE task_id = " . $taskId);

        // Delete children

        $conn->query("DELETE FROM task_questions   WHERE task_id = " . $taskId);

        $conn->query("DELETE FROM task_submissions WHERE task_id = " . $taskId);



        $del = $conn->prepare("DELETE FROM tasks WHERE id=? AND church_id=?");

        $del->bind_param('ii', $taskId, $churchId);

        $del->execute();



        $conn->commit();



        if (function_exists('writeAuditLog')) {

            writeAuditLog('delete', 'task', $taskId, 'deleted');

        }



        $msg = $reverseCoupons ? 'تم حذف المهمة وسحب الكوبونات' : 'تم حذف المهمة والكوبونات محتفظ بها';

        sendJSON(['success' => true, 'message' => $msg, 'coupons_reversed' => $totalReversed, 'reverse_coupons' => $reverseCoupons]);

    } catch (Exception $e) {

        if (isset($conn))

            $conn->rollback();

        error_log("deleteTask error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



// ─── getStudentTasks (kid-facing) ──────────────────────────────

function getStudentTasks()

{

    try {

        $conn = getDBConnection();

        $studentId = (int) ($_POST['student_id'] ?? 0);

        $churchId = (int) ($_POST['church_id'] ?? getChurchId());



        if (!$studentId) {

            sendJSON(['success' => false, 'message' => 'student_id مطلوب']);

            return;

        }



        // Get student class_id

        $sStmt = $conn->prepare("SELECT class_id FROM students WHERE id=? AND church_id=? LIMIT 1");

        $sStmt->bind_param('ii', $studentId, $churchId);

        $sStmt->execute();

        $stu = $sStmt->get_result()->fetch_assoc();

        if (!$stu) {

            sendJSON(['success' => false, 'message' => 'الطفل غير موجود']);

            return;

        }

        $classId = (int) $stu['class_id'];



        // Detect which optional task columns exist using SHOW COLUMNS (no information_schema needed)

        $allCols = [];

        $colRes = $conn->query("SHOW COLUMNS FROM tasks");

        while ($cr = $colRes->fetch_assoc())

            $allCols[] = $cr['Field'];



        $hasStatus = in_array('status', $allCols);

        $hasAssignTo = in_array('assign_to', $allCols);

        $hasTimer = in_array('timer_behavior', $allCols);

        $hasShuffle = in_array('shuffle', $allCols);

        $hasShow = in_array('show_result', $allCols);

        $hasShowAnswers = in_array('show_answers', $allCols);

        $hasReview = in_array('allow_review', $allCols);

        $hasIsActive = in_array('is_active', $allCols);

        $hasNoDeadline = in_array('no_deadline', $allCols);

        $hasClassIds = in_array('class_ids', $allCols);



        // Build SELECT dynamically based on existing columns

        $sel = "t.id, t.title, t.description, t.start_date, t.end_date,

                t.time_limit, t.total_degree, t.max_coupons, t.coupon_matrix,

                CASE

                    WHEN t.class_id = 0 THEN 'كل الفصول'

                    ELSE COALESCE(cc.arabic_name,'')

                END AS class_name";

        if ($hasStatus)

            $sel .= ", t.status";

        if ($hasAssignTo)

            $sel .= ", t.assign_to, t.specific_ids";

        if ($hasTimer)

            $sel .= ", t.timer_behavior";

        if ($hasShuffle)

            $sel .= ", t.shuffle";

        if ($hasShow)

            $sel .= ", t.show_result";

        if ($hasShowAnswers)

            $sel .= ", t.show_answers";

        if ($hasReview)

            $sel .= ", t.allow_review";

        if ($hasNoDeadline)

            $sel .= ", t.no_deadline";

        if ($hasClassIds)

            $sel .= ", t.class_ids";



        // Build WHERE — show all tasks (active, upcoming, expired) so kids can see their history

        // The JS tSt() function handles status badges; we just filter by class + church + active flag

        if ($hasClassIds) {

            $where = "t.church_id = ? AND (t.class_id = ? OR t.class_id = 0 OR FIND_IN_SET(?, t.class_ids))";

            $stmtParams = [$churchId, $classId, $classId];

            $stmtTypes = 'iii';

        } else {

            $where = "t.church_id = ? AND (t.class_id = ? OR t.class_id = 0)";

            $stmtParams = [$churchId, $classId];

            $stmtTypes = 'ii';

        }

        if ($hasStatus)

            $where .= " AND (t.status = 'published' OR t.status IS NULL)";

        if ($hasIsActive)

            $where .= " AND (t.is_active IS NULL OR t.is_active = 1)";



        $stmt = $conn->prepare("

            SELECT $sel

            FROM tasks t

            LEFT JOIN church_classes cc ON cc.id = t.class_id

            WHERE $where

            ORDER BY

                CASE WHEN t.end_date IS NULL OR " . ($hasNoDeadline ? "t.no_deadline = 1" : "0=1") . " THEN 1 ELSE 0 END,

                t.end_date ASC,

                t.start_date DESC

        ");

        $stmt->bind_param($stmtTypes, ...$stmtParams);

        $stmt->execute();

        $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);



        // Filter by specific_ids if assign_to column exists

        if ($hasAssignTo) {

            $tasks = array_values(array_filter($tasks, function ($t) use ($studentId) {

                if (($t['assign_to'] ?? 'all') === 'all')

                    return true;

                $ids = json_decode($t['specific_ids'] ?? '[]', true) ?: [];

                return in_array((int) $studentId, array_map('intval', $ids));

            }));

        }



        // Detect task_questions columns

        $qCols = [];

        $qColRes = $conn->query("SHOW COLUMNS FROM task_questions");

        while ($cr = $qColRes->fetch_assoc())

            $qCols[] = $cr['Field'];

        $qHasDegree = in_array('degree', $qCols);

        $qHasOrder = in_array('sort_order', $qCols);

        $qHasCorrect = in_array('correct_index', $qCols);

        $qHasType = in_array('question_type', $qCols);

        $qHasImageUrl = in_array('image_url', $qCols);



        // Always include question_type and image_url — they are essential for rendering

        $qSel = "id, question_text, options";

        if ($qHasType)

            $qSel .= ", question_type";

        if ($qHasDegree)

            $qSel .= ", degree";

        if ($qHasOrder)

            $qSel .= ", sort_order";

        if ($qHasCorrect)

            $qSel .= ", correct_index";

        if ($qHasImageUrl)

            $qSel .= ", image_url";



        // Detect task_submissions columns

        $sCols = [];

        $sColRes = $conn->query("SHOW COLUMNS FROM task_submissions");

        while ($cr = $sColRes->fetch_assoc())

            $sCols[] = $cr['Field'];

        $sHasScore = in_array('score', $sCols);

        $sHasCoupons = in_array('coupons_awarded', $sCols);

        $sHasSubmitAt = in_array('submitted_at', $sCols);



        $sSel = "id";

        if ($sHasScore)

            $sSel .= ", score";

        if ($sHasCoupons)

            $sSel .= ", coupons_awarded";

        if ($sHasSubmitAt)

            $sSel .= ", submitted_at";



        // Attach questions and submissions

        foreach ($tasks as &$t) {

            $t['timer_behavior'] = $t['timer_behavior'] ?? 'submit';

            $t['shuffle'] = isset($t['shuffle']) ? (int) $t['shuffle'] : 0;

            $t['show_result'] = isset($t['show_result']) ? (int) $t['show_result'] : 1;

            $t['show_answers'] = isset($t['show_answers']) ? (int) $t['show_answers'] : 0;

            $t['allow_review'] = isset($t['allow_review']) ? (int) $t['allow_review'] : 1;

            $t['no_deadline'] = isset($t['no_deadline']) ? (int) $t['no_deadline'] : 0;

            $t['assign_to'] = $t['assign_to'] ?? 'all';

            $t['specific_ids'] = $t['specific_ids'] ?? '[]';



            $qStmt = $conn->prepare("SELECT $qSel FROM task_questions WHERE task_id=? ORDER BY " . ($qHasOrder ? 'sort_order' : 'id'));

            $qStmt->bind_param('i', $t['id']);

            $qStmt->execute();

            $qs = $qStmt->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach ($qs as &$q) {

                $q['question_type'] = $q['question_type'] ?? 'mcq';

                $q['degree'] = isset($q['degree']) ? (int) $q['degree'] : 1;

                $q['sort_order'] = isset($q['sort_order']) ? (int) $q['sort_order'] : 0;

                $q['image_url'] = $q['image_url'] ?? '';

                // open questions must have correct_index = null (graded manually)

                if ($q['question_type'] === 'open') {

                    $q['correct_index'] = null;

                } else {

                    $q['correct_index'] = isset($q['correct_index']) ? (int) $q['correct_index'] : 0;

                }

            }

            unset($q);

            $t['questions'] = $qs;



            $subStmt = $conn->prepare("SELECT $sSel FROM task_submissions WHERE task_id=? AND student_id=? LIMIT 1");

            $subStmt->bind_param('ii', $t['id'], $studentId);

            $subStmt->execute();

            $subRow = $subStmt->get_result()->fetch_assoc();

            if ($subRow) {

                $mySubmission = [

                    'id' => $subRow['id'],

                    'score' => isset($subRow['score']) ? (int) $subRow['score'] : 0,

                    'coupons_awarded' => isset($subRow['coupons_awarded']) ? (int) $subRow['coupons_awarded'] : 0,

                    'submitted_at' => $subRow['submitted_at'] ?? null,

                ];



                // If show_answers is ON, attach the student's answers AND the correct answers

                // so both the kid view and uncle review can highlight right/wrong answers

                if (!empty($t['show_answers'])) {

                    // Fetch full answers blob from DB (not in $sSel yet)

                    $ansStmt = $conn->prepare("SELECT answers, open_scores, correction_notes FROM task_submissions WHERE id=? LIMIT 1");

                    $ansStmt->bind_param('i', $subRow['id']);

                    $ansStmt->execute();

                    $ansRow = $ansStmt->get_result()->fetch_assoc();

                    $mySubmission['answers'] = $ansRow ? json_decode($ansRow['answers'] ?? '{}', true) : [];

                    $mySubmission['open_scores'] = $ansRow ? json_decode($ansRow['open_scores'] ?? '{}', true) : [];

                    $mySubmission['correction_notes'] = $ansRow ? json_decode($ansRow['correction_notes'] ?? '{}', true) : [];



                    // Also attach correct answers for each question so frontend can color-code

                    $correctMap = [];

                    foreach ($t['questions'] as $q) {

                        if ($q['question_type'] !== 'open') {

                            $correctMap[$q['id']] = (int) $q['correct_index'];

                        }

                    }

                    $mySubmission['correct_answers'] = $correctMap;

                }



                $t['my_submission'] = $mySubmission;

            } else {

                $t['my_submission'] = null;

            }

        }

        unset($t);



        sendJSON(['success' => true, 'tasks' => $tasks]);

    } catch (Exception $e) {

        error_log("getStudentTasks error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



// ─── submitTaskAnswers (kid-facing) ────────────────────────────

function submitTaskAnswers()

{

    try {

        $conn = getDBConnection();

        $studentId = (int) ($_POST['student_id'] ?? 0);

        $churchId = (int) ($_POST['church_id'] ?? getChurchId());

        $taskId = (int) ($_POST['task_id'] ?? 0);

        $answersJson = $_POST['answers'] ?? '{}';

        $timeTaken = isset($_POST['time_taken_sec']) ? (int) $_POST['time_taken_sec'] : null;



        if (!$studentId || !$taskId) {

            sendJSON(['success' => false, 'message' => 'بيانات ناقصة']);

            return;

        }



        // Duplicate submission check

        $chk = $conn->prepare("SELECT id FROM task_submissions WHERE task_id=? AND student_id=?");

        $chk->bind_param('ii', $taskId, $studentId);

        $chk->execute();

        if ($chk->get_result()->num_rows > 0) {

            sendJSON(['success' => false, 'message' => 'لقد أرسلت إجاباتك بالفعل']);

            return;

        }



        // Load task

        $tStmt = $conn->prepare("SELECT * FROM tasks WHERE id=? AND church_id=? AND status='published' LIMIT 1");

        $tStmt->bind_param('ii', $taskId, $churchId);

        $tStmt->execute();

        $task = $tStmt->get_result()->fetch_assoc();

        if (!$task) {

            sendJSON(['success' => false, 'message' => 'المهمة غير متاحة']);

            return;

        }



        // Load questions with correct answers

        $qStmt = $conn->prepare("SELECT id, correct_index, degree FROM task_questions WHERE task_id=? ORDER BY sort_order");

        $qStmt->bind_param('i', $taskId);

        $qStmt->execute();

        $questions = $qStmt->get_result()->fetch_all(MYSQLI_ASSOC);



        // Score

        $answers = json_decode($answersJson, true) ?: [];

        $score = 0;

        foreach ($questions as $q) {

            $given = $answers[$q['id']] ?? $answers[(string) $q['id']] ?? null;

            // Skip open questions (correct_index is NULL — graded manually)

            if ($q['correct_index'] === null)

                continue;

            if ($given !== null && (int) $given === (int) $q['correct_index']) {

                $score += (int) $q['degree'];

            }

        }



        // Coupons from matrix

        $pct = $task['total_degree'] > 0 ? ($score / $task['total_degree'] * 100) : 0;

        $matrix = json_decode($task['coupon_matrix'] ?? '[]', true) ?: [];

        $coupons = 0;

        foreach ($matrix as $tier) {

            if ($pct >= (float) $tier['from'] && $pct <= (float) $tier['to']) {

                $coupons = (int) $tier['val'];

                break;

            }

        }



        $conn->begin_transaction();



        // Insert submission — submitted_at uses NOW() inline so only 7 bind vars

        $ins = $conn->prepare("

            INSERT INTO task_submissions

                (task_id, student_id, church_id, answers, score, coupons_awarded, submitted_at, time_taken_sec)

            VALUES (?,?,?,?,?,?,NOW(),?)

        ");

        $nullableTime = ($timeTaken !== null) ? (int) $timeTaken : null;

        $ins->bind_param('iiisiii', $taskId, $studentId, $churchId, $answersJson, $score, $coupons, $nullableTime);

        $ins->execute();



        // Award coupons — update task_coupons AND recalculate total coupons

        if ($coupons > 0) {

            $cur = $conn->prepare("SELECT name, coupons, task_coupons, attendance_coupons, commitment_coupons FROM students WHERE id=? LIMIT 1");

            $cur->bind_param('i', $studentId);

            $cur->execute();

            $stu = $cur->get_result()->fetch_assoc();



            $newTask = (int) $stu['task_coupons'] + $coupons;

            $newTotal = $newTask + (int) $stu['attendance_coupons'] + (int) $stu['commitment_coupons'];



            $upd = $conn->prepare("UPDATE students SET task_coupons=?, coupons=? WHERE id=?");

            $upd->bind_param('iii', $newTask, $newTotal, $studentId);

            $upd->execute();



            // Log in coupon_logs

            $log = $conn->prepare("

                INSERT INTO coupon_logs

                    (student_id, uncle_id, old_count, new_count, change_amount, change_type, reason)

                VALUES (?, NULL, ?, ?, ?, 'task', ?)

            ");

            $reason = "مهمة #{$taskId}: {$task['title']}";

            $log->bind_param('iiiis', $studentId, $stu['task_coupons'], $newTask, $coupons, $reason);

            $log->execute();



            // ► AUDIT

            auditCouponChange($studentId, $stu['name'] ?? '', (int) $stu['coupons'], $newTotal, $reason);

        }



        $conn->commit();



        // Push notification to church dashboard

        $stuRow = $conn->query("SELECT name FROM students WHERE id=$studentId LIMIT 1")->fetch_assoc();

        $stuName = $stuRow['name'] ?? 'طفل';

        pushNotification(

            $conn,

            $churchId,

            'task_submission',

            'تسليم مهمة جديدة',

            "{$stuName} سلّم مهمة «{$task['title']}» بدرجة {$score} من {$task['total_degree']}",

            'task',

            $taskId

        );



        $result = [

            'success' => true,

            'score' => $score,

            'total_degree' => (int) $task['total_degree'],

            'percentage' => round($pct, 1),

            'coupons_awarded' => $coupons,

            'show_result' => (bool) (int) $task['show_result'],

            'show_answers' => (bool) (int) ($task['show_answers'] ?? 0),

            'message' => "أحسنت! درجتك {$score} من {$task['total_degree']} — حصلت على {$coupons} كوبون"

        ];



        // Include answers + correct indices so kid can review which were right/wrong

        if (!empty($task['show_answers'])) {

            // Return full questions with correct_index so frontend can highlight

            $qaStmt = $conn->prepare("SELECT id, question_type, question_text, options, correct_index, degree, sort_order FROM task_questions WHERE task_id=? ORDER BY sort_order");

            $qaStmt->bind_param('i', $taskId);

            $qaStmt->execute();

            $result['questions_with_answers'] = $qaStmt->get_result()->fetch_all(MYSQLI_ASSOC);

            $result['submitted_answers'] = $answers; // the student's own choices

        }



        if (!$result['show_result']) {

            unset($result['score'], $result['percentage'], $result['coupons_awarded']);

            $result['message'] = 'تم إرسال إجاباتك بنجاح';

        }



        sendJSON($result);

    } catch (Exception $e) {

        if (isset($conn))

            $conn->rollback();

        error_log("submitTaskAnswers error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}





// ─── fetchOgImage — extract og:image or first <img> from any URL ──

function fetchOgImage()

{

    try {

        $url = $_POST['url'] ?? '';

        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {

            sendJSON(['success' => false, 'message' => 'رابط غير صالح']);

            return;

        }

        // Fetch the page (timeout 6s, follow redirects)

        $ctx = stream_context_create([

            'http' => [

                'timeout' => 6,

                'follow_location' => 1,

                'max_redirects' => 5,

                'user_agent' => 'Mozilla/5.0 (compatible; SundaySchoolBot/1.0)',

                'ignore_errors' => true,

            ]

        ]);

        $html = @file_get_contents($url, false, $ctx);

        if (!$html) {

            sendJSON(['success' => false, 'message' => 'تعذّر جلب الصفحة']);

            return;

        }



        // 1. Try og:image

        if (

            preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)

            || preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $html, $m)

        ) {

            sendJSON(['success' => true, 'image_url' => htmlspecialchars_decode($m[1])]);

            return;

        }

        // 2. Try twitter:image

        if (preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {

            sendJSON(['success' => true, 'image_url' => htmlspecialchars_decode($m[1])]);

            return;

        }

        // 3. First <img> with src

        if (preg_match('/<img[^>]+src=["\']([^"\']+\.(?:jpg|jpeg|png|gif|webp))["\']/i', $html, $m)) {

            $imgSrc = $m[1];

            if (strpos($imgSrc, 'http') !== 0) {

                $parsed = parse_url($url);

                $imgSrc = $parsed['scheme'] . '://' . $parsed['host'] . $imgSrc;

            }

            sendJSON(['success' => true, 'image_url' => $imgSrc]);

            return;

        }

        sendJSON(['success' => false, 'message' => 'لم يُعثر على صورة في هذا الرابط']);

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



// ─── getExamStart — READ-ONLY check: has this student already started? ──

// Returns {success:true, started_at:...} if a record exists, or {success:true} with no started_at if not.

// Does NOT create a record — that's startExam's job.

function getExamStart()

{

    try {

        $conn = getDBConnection();

        $studentId = (int) ($_POST['student_id'] ?? 0);

        $taskId = (int) ($_POST['task_id'] ?? 0);



        if (!$studentId || !$taskId) {

            sendJSON(['success' => true]);

            return;

        }



        $tbl = $conn->query("SHOW TABLES LIKE 'exam_starts'")->fetch_assoc();

        if (!$tbl) {

            sendJSON(['success' => true]);

            return;

        }



        $sel = $conn->prepare("SELECT started_at FROM exam_starts WHERE task_id=? AND student_id=? LIMIT 1");

        $sel->bind_param('ii', $taskId, $studentId);

        $sel->execute();

        $row = $sel->get_result()->fetch_assoc();



        if ($row) {

            sendJSON(['success' => true, 'started_at' => $row['started_at']]);

        } else {

            sendJSON(['success' => true]); // no record yet — not started

        }

    } catch (Exception $e) {

        sendJSON(['success' => true]); // non-fatal fallback

    }

}



// ─── startExam — record when a student begins a timed task ────────

// Creates/updates a lightweight record so the timer is server-anchored.

// The JS uses the returned started_at to compute remaining time accurately

// even after closing and reopening the page.

function startExam()

{

    try {

        $conn = getDBConnection();

        $studentId = (int) ($_POST['student_id'] ?? 0);

        $churchId = (int) ($_POST['church_id'] ?? getChurchId());

        $taskId = (int) ($_POST['task_id'] ?? 0);



        if (!$studentId || !$taskId) {

            sendJSON(['success' => false, 'message' => 'بيانات ناقصة']);

            return;

        }



        // Ensure the exam_starts table exists (auto-migrate)

        $conn->query("

            CREATE TABLE IF NOT EXISTS exam_starts (

                id          INT AUTO_INCREMENT PRIMARY KEY,

                task_id     INT NOT NULL,

                student_id  INT NOT NULL,

                church_id   INT NOT NULL,

                started_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                UNIQUE KEY uniq_exam_start (task_id, student_id)

            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4

        ");



        // Insert only if not already started (preserve original start time)

        $ins = $conn->prepare("

            INSERT IGNORE INTO exam_starts (task_id, student_id, church_id, started_at)

            VALUES (?, ?, ?, NOW())

        ");

        $ins->bind_param('iii', $taskId, $studentId, $churchId);

        $ins->execute();



        // Return the actual start time (original or just-inserted)

        $sel = $conn->prepare("SELECT started_at FROM exam_starts WHERE task_id=? AND student_id=? LIMIT 1");

        $sel->bind_param('ii', $taskId, $studentId);

        $sel->execute();

        $row = $sel->get_result()->fetch_assoc();



        sendJSON(['success' => true, 'started_at' => $row['started_at'] ?? date('Y-m-d H:i:s')]);

    } catch (Exception $e) {

        // Non-fatal — fall back gracefully, client will use local time

        sendJSON(['success' => true, 'started_at' => date('Y-m-d H:i:s'), 'fallback' => true]);

    }

}



// ─── clearExamStart — delete a stale timer record so student can restart ──

function clearExamStart()

{

    try {

        $conn = getDBConnection();

        $studentId = (int) ($_POST['student_id'] ?? 0);

        $taskId = (int) ($_POST['task_id'] ?? 0);



        if (!$studentId || !$taskId) {

            sendJSON(['success' => true]);

            return;

        }



        // Only delete if no submission exists — never erase a completed exam record

        $chk = $conn->prepare("SELECT id FROM task_submissions WHERE task_id=? AND student_id=? LIMIT 1");

        $chk->bind_param('ii', $taskId, $studentId);

        $chk->execute();

        if ($chk->get_result()->num_rows > 0) {

            // Student already submitted — do NOT clear, they are done

            sendJSON(['success' => false, 'message' => 'submission_exists']);

            return;

        }



        // Safe to delete the stale start record

        $del = $conn->prepare("DELETE FROM exam_starts WHERE task_id=? AND student_id=?");

        $del->bind_param('ii', $taskId, $studentId);

        $del->execute();



        sendJSON(['success' => true]);

    } catch (Exception $e) {

        sendJSON(['success' => true]); // non-fatal

    }

}





function _insertTaskQuestions($conn, $taskId, array $questions)

{

    if (!$questions)

        return;



    // Ensure question_type column exists

    $conn->query("ALTER TABLE task_questions ADD COLUMN IF NOT EXISTS question_type ENUM('mcq','open','tf') NOT NULL DEFAULT 'mcq' AFTER id");

    $conn->query("ALTER TABLE task_questions ADD COLUMN IF NOT EXISTS image_url TEXT DEFAULT NULL");

    // Ensure correct_index is nullable (handles existing NOT NULL columns)

    $conn->query("ALTER TABLE task_questions MODIFY COLUMN correct_index INT DEFAULT NULL");

    $conn->query("ALTER TABLE task_questions ADD COLUMN IF NOT EXISTS degree INT NOT NULL DEFAULT 1 AFTER correct_index");

    $conn->query("ALTER TABLE task_questions ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0 AFTER degree");

    $conn->query("ALTER TABLE task_questions ADD COLUMN IF NOT EXISTS image_url TEXT DEFAULT NULL");



    $stmt = $conn->prepare("

        INSERT INTO task_questions

            (task_id, question_type, question_text, options, correct_index, degree, sort_order, image_url)

        VALUES (?,?,?,?,?,?,?,?)

    ");

    foreach ($questions as $i => $q) {

        $type = in_array($q['question_type'] ?? 'mcq', ['mcq', 'open', 'tf']) ? $q['question_type'] : 'mcq';

        $text = $q['question_text'] ?? '';

        $opts = $type === 'open' ? '[]' : (is_array($q['options'])

            ? json_encode($q['options'], JSON_UNESCAPED_UNICODE)

            : ($q['options'] ?? '[]'));

        // open questions have no correct answer — pass NULL as string binding to avoid NOT NULL errors

        $correct = ($type === 'open') ? null : (int) ($q['correct_index'] ?? 0);

        $degree = (int) ($q['degree'] ?? 1);

        $order = (int) ($q['sort_order'] ?? $i);

        $imageUrl = $q['image_url'] ?? '';

        // Use 's' (string) for correct_index so PHP can pass NULL without type mismatch

        $stmt->bind_param('issssiis', $taskId, $type, $text, $opts, $correct, $degree, $order, $imageUrl);

        $stmt->execute();

    }

}



// ─── getStudentTrips (no auth required) ──────────────────────────

// Returns trips for a church with registered kids (photos) + own payment.

function getStudentTrips()

{

    try {

        $conn = getDBConnection();

        $churchId = (int) ($_POST['church_id'] ?? $_GET['church_id'] ?? 0);

        $studentId = (int) ($_POST['student_id'] ?? $_GET['student_id'] ?? 0);

        if (!$churchId) {

            sendJSON(['success' => false, 'message' => 'church_id مطلوب']);

            return;

        }



        $stmt = $conn->prepare("

            SELECT

                t.id, t.title, t.description, t.type,

                t.start_date, t.end_date, t.status,

                t.price, t.discount, t.discount_type,

                t.max_participants, t.image_url,

                COALESCE(t.show_registered_kids, 1) AS show_registered_kids

            FROM trips t

            WHERE t.church_id = ?

              AND t.status IN ('planned','active','completed')

            ORDER BY FIELD(t.status,'active','planned','completed'), t.start_date DESC

            LIMIT 20

        ");

        $stmt->bind_param('i', $churchId);

        $stmt->execute();

        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);



        foreach ($rows as &$row) {

            $price = (float) $row['price'];

            $disc = (float) $row['discount'];

            $final = $price;

            if ($disc > 0) {

                $final = $row['discount_type'] === 'percentage'

                    ? $price - ($price * $disc / 100)

                    : max(0, $price - $disc);

            }

            $row['final_price'] = round($final, 2);

            unset($row['price'], $row['discount'], $row['discount_type']);

            $row['start_date_formatted'] = $row['start_date'] ? date('d/m/Y', strtotime($row['start_date'])) : '';

            $row['end_date_formatted'] = $row['end_date'] ? date('d/m/Y', strtotime($row['end_date'])) : '';



            $cStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM trip_registrations WHERE trip_id = ? AND cancelled = 0");

            $cStmt->bind_param('i', $row['id']);

            $cStmt->execute();

            $row['registered_count'] = (int) ($cStmt->get_result()->fetch_assoc()['cnt'] ?? 0);



            // Registered kids (name + photo only, no payments) can be hidden per trip.

            $row['show_registered_kids'] = (int) ($row['show_registered_kids'] ?? 1);

            $row['registered_kids'] = [];

            if ($row['show_registered_kids'] === 1) {

                $rStmt = $conn->prepare("

                    SELECT s.id, s.name, s.image_url,

                           COALESCE(cc.arabic_name, cl.arabic_name, s.class) AS class

                    FROM trip_registrations tr

                    JOIN students s ON s.id = tr.student_id

                    LEFT JOIN church_classes cc ON cc.id = s.class_id AND cc.church_id = s.church_id

                    LEFT JOIN classes cl ON cl.id = s.class_id

                    WHERE tr.trip_id = ? AND tr.cancelled = 0

                    ORDER BY s.name

                ");

                $rStmt->bind_param('i', $row['id']);

                $rStmt->execute();

                $row['registered_kids'] = $rStmt->get_result()->fetch_all(MYSQLI_ASSOC);

            }



            // Current student's own registration + payment

            $row['my_registration'] = null;

            if ($studentId) {

                $mStmt = $conn->prepare("

                    SELECT tr.id, tr.payment_status, tr.amount_paid,

                           COALESCE((SELECT SUM(amount) FROM trip_payments WHERE registration_id=tr.id AND is_deleted = 0),0) AS total_paid

                    FROM trip_registrations tr

                    WHERE tr.trip_id=? AND tr.student_id=? AND tr.cancelled=0

                    LIMIT 1

                ");

                $mStmt->bind_param('ii', $row['id'], $studentId);

                $mStmt->execute();

                $myReg = $mStmt->get_result()->fetch_assoc();

                if ($myReg) {

                    $myReg['total_paid'] = (float) $myReg['total_paid'];

                    $myReg['remaining'] = max(0, round($row['final_price'] - $myReg['total_paid'], 2));

                    $row['my_registration'] = $myReg;

                }

                // Also attach this student's points for this trip (if stored)

                try {

                    $pStmt = $conn->prepare("SELECT trip_points FROM students WHERE id = ? AND church_id = ? LIMIT 1");

                    $pStmt->bind_param('ii', $studentId, $churchId);

                    $pStmt->execute();

                    $pRes = $pStmt->get_result()->fetch_assoc();

                    $tp = $pRes['trip_points'] ?? '';

                    $pointsMap = json_decode($tp, true);

                    if (!is_array($pointsMap))

                        $pointsMap = [];

                    $row['my_points'] = intval($pointsMap[$row['id']] ?? 0);

                } catch (Exception $e) {

                    $row['my_points'] = 0;

                }

            }

        }

        unset($row);



        sendJSON(['success' => true, 'trips' => $rows]);

    } catch (Exception $e) {

        error_log("getStudentTrips error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



// ─── getPublicChurchSettings (no auth required) ───────────────────

// Returns attendance_day and custom_fields for a church by ID.

function getPublicChurchSettings()

{

    try {

        $conn = getDBConnection();

        $churchId = (int) ($_POST['church_id'] ?? $_GET['church_id'] ?? 0);

        if (!$churchId) {

            sendJSON(['success' => false, 'message' => 'church_id مطلوب']);

            return;

        }



        $stmt = $conn->prepare("SELECT attendance_day, custom_field FROM church_settings WHERE church_id=? LIMIT 1");

        $stmt->bind_param('i', $churchId);

        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();



        $day = $row ? (int) $row['attendance_day'] : 5;

        $cf = null;

        if ($row && !empty($row['custom_field'])) {

            $raw = json_decode($row['custom_field'], true);

            if (is_array($raw)) {

                $cf = isset($raw[0]) ? $raw : [$raw];

            }

        }

        sendJSON(['success' => true, 'attendance_day' => $day, 'custom_fields' => $cf]);

    } catch (Exception $e) {

        sendJSON(['success' => true, 'attendance_day' => 5, 'custom_fields' => null]);

    }

}



// ─── getStudentTripDetails (public — registered kids + own payment) ─

function getStudentTripDetails_pub()

{

    // intentionally left empty — merged into getStudentTrips below

}



// ─── getPublicClassUncles (no auth — by church_id + class_name) ───

function getPublicClassUncles()

{

    try {

        $conn = getDBConnection();

        $churchId = (int) ($_POST['church_id'] ?? $_GET['church_id'] ?? 0);

        $className = sanitize($_POST['class_name'] ?? $_GET['class_name'] ?? '');

        $classId = (int) ($_POST['class_id'] ?? $_GET['class_id'] ?? 0);



        if (!$churchId) {

            sendJSON(['success' => true, 'uncles' => []]);

            return;

        }



        // If class_name not provided but class_id is, resolve the name

        if (empty($className) && $classId > 0) {

            $nStmt = $conn->prepare("SELECT arabic_name FROM church_classes WHERE id=? AND church_id=? LIMIT 1");

            $nStmt->bind_param('ii', $classId, $churchId);

            $nStmt->execute();

            $nRow = $nStmt->get_result()->fetch_assoc();

            if ($nRow)

                $className = $nRow['arabic_name'];

        }



        if (empty($className)) {

            sendJSON(['success' => true, 'uncles' => []]);

            return;

        }



        $stmt = $conn->prepare("

            SELECT u.id, u.name, u.image_url, u.role, u.phone

            FROM uncle_class_assignments a

            JOIN uncles u ON a.uncle_id = u.id

            WHERE a.church_id = ? AND a.class_name = ?

              AND (u.deleted IS NULL OR u.deleted = 0)

            ORDER BY CASE u.role WHEN 'admin' THEN 1 WHEN 'developer' THEN 2 ELSE 3 END, u.name

        ");

        $stmt->bind_param('is', $churchId, $className);

        $stmt->execute();

        $uncles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        sendJSON(['success' => true, 'uncles' => $uncles, 'resolved_class_name' => $className]);

    } catch (Exception $e) {

        sendJSON(['success' => true, 'uncles' => []]);

    }

}





// ─── debugKidProfile — call with ?action=debugKidProfile&phone=01XXXXXXXXX ──

function debugKidProfile()

{

    $conn = getDBConnection();

    $phone = sanitize($_POST['phone'] ?? $_GET['phone'] ?? '');

    $clean = preg_replace('/[^\d]/', '', $phone);



    // 1. Raw student row

    $s = $conn->query("SELECT id, name, class_id, class, church_id, phone FROM students WHERE phone LIKE '%$clean%' OR phone = '$clean' LIMIT 5");

    $students = $s ? $s->fetch_all(MYSQLI_ASSOC) : [];



    $out = ['students_raw' => $students, 'class_lookups' => []];



    foreach ($students as $stu) {

        $cid = (int) $stu['class_id'];

        $chid = (int) $stu['church_id'];



        // 2. church_classes lookup

        $cc = $conn->query("SELECT id, arabic_name, is_active, church_id FROM church_classes WHERE id = $cid AND church_id = $chid LIMIT 1");

        $ccRow = $cc ? $cc->fetch_assoc() : null;



        // 3. global classes lookup

        $gc = $conn->query("SELECT id, arabic_name FROM classes WHERE id = $cid LIMIT 1");

        $gcRow = $gc ? $gc->fetch_assoc() : null;



        // 4. uncle_class_assignments

        $ua = $conn->query("SELECT a.class_name, u.name, u.image_url FROM uncle_class_assignments a JOIN uncles u ON u.id=a.uncle_id WHERE a.church_id=$chid LIMIT 10");

        $uRows = $ua ? $ua->fetch_all(MYSQLI_ASSOC) : [];



        // 5. tasks — full row to see is_active etc

        $tasks = $conn->query("SELECT * FROM tasks WHERE church_id=$chid LIMIT 5");

        $tRows = $tasks ? $tasks->fetch_all(MYSQLI_ASSOC) : [];



        // 5b. task_questions columns

        $qcols = $conn->query("SHOW COLUMNS FROM task_questions");

        $qColNames = [];

        while ($r = $qcols->fetch_assoc())

            $qColNames[] = $r['Field'];



        // 5c. task_submissions columns

        $scols = $conn->query("SHOW COLUMNS FROM task_submissions");

        $sColNames = [];

        while ($r = $scols->fetch_assoc())

            $sColNames[] = $r['Field'];



        $out['task_questions_columns'] = $qColNames;

        $out['task_submissions_columns'] = $sColNames;



        // 6. task columns

        $cols = $conn->query("SHOW COLUMNS FROM tasks");

        $colNames = [];

        while ($r = $cols->fetch_assoc())

            $colNames[] = $r['Field'];



        $out['class_lookups'][] = [

            'student_id' => $stu['id'],

            'student_name' => $stu['name'],

            'class_id' => $cid,

            'class_text_col' => $stu['class'],

            'church_id' => $chid,

            'church_class_row' => $ccRow,

            'global_class_row' => $gcRow,

            'coalesce_result' => $ccRow['arabic_name'] ?? ($gcRow['arabic_name'] ?? $stu['class'] ?? 'NULL'),

            'uncle_assignments' => $uRows,

            'tasks_in_church' => $tRows,

            'tasks_columns' => $colNames,

        ];

    }



    header('Content-Type: application/json');

    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    exit;

}



// ════════════════════════════════════════════════════════════════

// NOTIFICATIONS

// ════════════════════════════════════════════════════════════════



function ensureNotificationsTable($conn)

{

    $conn->query("

        CREATE TABLE IF NOT EXISTS `notifications` (

            `id`          INT AUTO_INCREMENT PRIMARY KEY,

            `church_id`   INT NOT NULL,

            `type`        VARCHAR(50) NOT NULL DEFAULT 'system',

            `title`       VARCHAR(300) NOT NULL,

            `body`        TEXT DEFAULT NULL,

            `entity_type` VARCHAR(50) DEFAULT NULL,

            `entity_id`   INT DEFAULT NULL,

            `is_read`     TINYINT(1) NOT NULL DEFAULT 0,

            `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_church_read (`church_id`, `is_read`)

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci

    ");

}



function getNotifications()

{

    try {

        $conn = getDBConnection();

        $churchId = getChurchId();

        ensureNotificationsTable($conn);



        $limit = (int) ($_POST['limit'] ?? 50);

        $offset = (int) ($_POST['offset'] ?? 0);



        $stmt = $conn->prepare("

            SELECT id, type, title, body, entity_type, entity_id, is_read, created_at

            FROM notifications

            WHERE church_id = ?

            ORDER BY created_at DESC

            LIMIT ? OFFSET ?

        ");

        $stmt->bind_param('iii', $churchId, $limit, $offset);

        $stmt->execute();

        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);



        $countStmt = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE church_id=? AND is_read=0");

        $countStmt->bind_param('i', $churchId);

        $countStmt->execute();

        $unread = (int) $countStmt->get_result()->fetch_assoc()['c'];



        sendJSON(['success' => true, 'notifications' => $rows, 'unread_count' => $unread]);

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



function markNotificationRead()

{

    try {

        $conn = getDBConnection();

        $churchId = getChurchId();

        $id = (int) ($_POST['id'] ?? 0);

        ensureNotificationsTable($conn);

        $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND church_id=?");

        $stmt->bind_param('ii', $id, $churchId);

        $stmt->execute();

        sendJSON(['success' => true]);

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



function deleteNotification()

{

    try {

        $conn = getDBConnection();

        $churchId = getChurchId();

        $id = (int) ($_POST['id'] ?? 0);

        ensureNotificationsTable($conn);

        $stmt = $conn->prepare("DELETE FROM notifications WHERE id=? AND church_id=?");

        $stmt->bind_param('ii', $id, $churchId);

        $stmt->execute();

        sendJSON(['success' => true]);

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



function markAllNotificationsRead()

{

    try {

        $conn = getDBConnection();

        $churchId = getChurchId();

        ensureNotificationsTable($conn);

        $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE church_id=? AND is_read=0");

        $stmt->bind_param('i', $churchId);

        $stmt->execute();

        sendJSON(['success' => true]);

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



// Helper — create a notification automatically

function pushNotification($conn, $churchId, $type, $title, $body = '', $entityType = null, $entityId = null, $classId = null)

{

    try {

        ensureNotificationsTable($conn);

        // Append routing info to body if classId provided

        $fullBody = $body;

        if ($classId !== null) {

            $fullBody = $body . '|||class_id:' . intval($classId);

        }

        $stmt = $conn->prepare("

            INSERT INTO notifications (church_id, type, title, body, entity_type, entity_id)

            VALUES (?,?,?,?,?,?)

        ");

        $stmt->bind_param('issssi', $churchId, $type, $title, $fullBody, $entityType, $entityId);

        $stmt->execute();

    } catch (Exception $e) {

        error_log("pushNotification error: " . $e->getMessage());

    }

}



// ════════════════════════════════════════════════════════════════

// DEVELOPER MESSAGES

// ════════════════════════════════════════════════════════════════



function ensureDevMessagesTable($conn)

{

    $conn->query("

        CREATE TABLE IF NOT EXISTS `developer_messages` (

            `id`             INT AUTO_INCREMENT PRIMARY KEY,

            `to_church_id`   INT NOT NULL DEFAULT 0 COMMENT '0 = broadcast to all churches',

            `subject`        VARCHAR(300) NOT NULL,

            `body`           TEXT NOT NULL,

            `is_read`        TINYINT(1) NOT NULL DEFAULT 0,

            `is_deleted`     TINYINT(1) NOT NULL DEFAULT 0,

            `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_target (`to_church_id`, `is_deleted`),

            INDEX idx_read (`is_deleted`, `is_read`)

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci

    ");

    // Migrate old table if it has wrong columns

    $cols = $conn->query("SHOW COLUMNS FROM developer_messages")->fetch_all(MYSQLI_ASSOC);

    $colNames = array_column($cols, 'Field');

    if (in_array('from_church_id', $colNames) && !in_array('to_church_id', $colNames)) {

        $conn->query("ALTER TABLE developer_messages ADD COLUMN to_church_id INT NOT NULL DEFAULT 0 AFTER id");

        $conn->query("ALTER TABLE developer_messages DROP COLUMN from_church_id");

        $conn->query("ALTER TABLE developer_messages DROP COLUMN from_name");

    }

}



function sendDeveloperMessage()

{

    // Only developers can send messages TO churches

    try {

        $role = strtolower($_SESSION['uncle_role'] ?? '');

        if (!in_array($role, ['developer', 'dev'])) {

            sendJSON(['success' => false, 'message' => 'فقط المطور يمكنه إرسال الرسائل']);

            return;

        }

        $conn = getDBConnection();

        $toChurchId = (int) ($_POST['to_church_id'] ?? 0); // 0 = broadcast to all

        $subject = sanitize($_POST['subject'] ?? '');

        $body = sanitize($_POST['body'] ?? '');



        if (!$subject || !$body) {

            sendJSON(['success' => false, 'message' => 'الموضوع والرسالة مطلوبان']);

            return;

        }



        ensureDevMessagesTable($conn);

        $stmt = $conn->prepare("INSERT INTO developer_messages (to_church_id, subject, body) VALUES (?,?,?)");

        $stmt->bind_param('iss', $toChurchId, $subject, $body);

        $stmt->execute();

        $msgId = $conn->insert_id;



        // Push a notification to the targeted church(es)

        if ($toChurchId > 0) {

            pushNotification($conn, $toChurchId, 'developer_message', $subject, $body, 'developer_message', $msgId);

            // Also send web push to subscribed devices of that church

            _sendWebPushToChurch($conn, $toChurchId, $subject, $body, ['notifType' => 'developer_message']);

        } else {

            // Broadcast — push to ALL churches

            $churches = $conn->query("SELECT id FROM churches WHERE 1 LIMIT 200")->fetch_all(MYSQLI_ASSOC);

            foreach ($churches as $ch) {

                pushNotification($conn, (int) $ch['id'], 'developer_message', $subject, $body, 'developer_message', $msgId);

                _sendWebPushToChurch($conn, (int) $ch['id'], $subject, $body, ['notifType' => 'developer_message']);

            }

        }



        sendJSON(['success' => true, 'message' => 'تم الإرسال للكنيسة/الكنائس بنجاح']);

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



function getDeveloperMessages()

{

    try {

        $conn = getDBConnection();

        ensureDevMessagesTable($conn);

        $role = strtolower($_SESSION['uncle_role'] ?? '');

        $isDev = in_array($role, ['developer', 'dev']);



        if ($isDev) {

            // Developer sees ALL messages they sent (outbox)

            $rows = $conn->query("SELECT * FROM developer_messages WHERE is_deleted=0 ORDER BY created_at DESC LIMIT 200")->fetch_all(MYSQLI_ASSOC);

            $unread = 0;

        } else {

            // Church sees messages addressed to them (to_church_id=their id OR broadcast 0)

            $churchId = getChurchId();

            if (!$churchId) {

                sendJSON(['success' => false, 'message' => 'غير مصرح']);

                return;

            }

            $stmt = $conn->prepare("SELECT * FROM developer_messages WHERE is_deleted=0 AND (to_church_id=? OR to_church_id=0) ORDER BY created_at DESC LIMIT 100");

            $stmt->bind_param('i', $churchId);

            $stmt->execute();

            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            $unread = count(array_filter($rows, fn($r) => !$r['is_read']));

        }

        sendJSON(['success' => true, 'messages' => $rows, 'unread_count' => (int) $unread, 'is_developer' => $isDev]);

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



// Helper: send web push to all devices subscribed for a church

function _sendWebPushToChurch($conn, $churchId, $title, $body, $extra = [])

{

    try {

        // Requires push_subscriptions table and VAPID key

        $vapid = defined('VAPID_PRIVATE_KEY') ? VAPID_PRIVATE_KEY : (getenv('VAPID_PRIVATE_KEY') ?: '');

        $vapidPub = defined('VAPID_PUBLIC_KEY') ? VAPID_PUBLIC_KEY : (getenv('VAPID_PUBLIC_KEY') ?: '');

        if (!$vapid || !$vapidPub)

            return;



        $tbl = $conn->query("SHOW TABLES LIKE 'push_subscriptions'")->fetch_assoc();

        if (!$tbl)

            return;



        $stmt = $conn->prepare("SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE church_id=? AND uncle_id IS NOT NULL LIMIT 50");

        $stmt->bind_param('i', $churchId);

        $stmt->execute();

        $subs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (!$subs)

            return;



        // Use the same sendPushToSubscription helper if it exists

        if (function_exists('_pushToEndpoint')) {

            foreach ($subs as $sub) {

                _pushToEndpoint(

                    $sub['endpoint'],

                    $sub['p256dh'],

                    $sub['auth'],

                    json_encode(array_merge(['title' => $title, 'body' => $body], $extra))

                );

            }

        }

    } catch (Exception $e) {

        error_log("_sendWebPushToChurch error: " . $e->getMessage());

    }

}



function markDevMessageRead()

{

    try {

        $conn = getDBConnection();

        $id = (int) ($_POST['id'] ?? 0);

        ensureDevMessagesTable($conn);

        $conn->query("UPDATE developer_messages SET is_read=1 WHERE id=$id");

        sendJSON(['success' => true]);

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



function deleteDevMessage()

{

    try {

        $conn = getDBConnection();

        $id = (int) ($_POST['id'] ?? 0);

        ensureDevMessagesTable($conn);

        $conn->query("UPDATE developer_messages SET is_deleted=1 WHERE id=$id");

        sendJSON(['success' => true]);

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



// ════════════════════════════════════════════════════════════════

// PUSH SUBSCRIPTIONS  (web push / service worker)

// ════════════════════════════════════════════════════════════════



function savePushSubscription()

{

    try {

        $conn = getDBConnection();

        $endpoint = $_POST['endpoint'] ?? '';

        $p256dh = $_POST['p256dh'] ?? '';

        $auth = $_POST['auth'] ?? '';

        $uncleId = isset($_SESSION['uncle_id']) ? (int) $_SESSION['uncle_id'] : null;

        $churchId = getChurchId();



        if (!$endpoint) {

            sendJSON(['success' => false, 'message' => 'endpoint مطلوب']);

            return;

        }



        $conn->query("

            CREATE TABLE IF NOT EXISTS push_subscriptions (

                id         INT AUTO_INCREMENT PRIMARY KEY,

                church_id  INT NOT NULL DEFAULT 0,

                uncle_id   INT DEFAULT NULL,

                endpoint   TEXT NOT NULL,

                p256dh     TEXT NOT NULL,

                auth       VARCHAR(100) NOT NULL,

                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uq_endpoint (endpoint(200))

            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4

        ");



        $stmt = $conn->prepare("

            INSERT INTO push_subscriptions (church_id, uncle_id, endpoint, p256dh, auth)

            VALUES (?,?,?,?,?)

            ON DUPLICATE KEY UPDATE church_id=VALUES(church_id), uncle_id=VALUES(uncle_id), p256dh=VALUES(p256dh), auth=VALUES(auth), updated_at=NOW()

        ");

        $stmt->bind_param('iisss', $churchId, $uncleId, $endpoint, $p256dh, $auth);

        $stmt->execute();

        sendJSON(['success' => true]);

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



function sendPushNotificationAction()

{

    try {

        $conn = getDBConnection();

        $target = $_POST['target'] ?? 'self';   // 'self' | 'church'

        $title = sanitize($_POST['title'] ?? 'إشعار');

        $body = sanitize($_POST['body'] ?? '');

        $url = $_POST['url'] ?? '/';

        $notifType = sanitize($_POST['notifType'] ?? 'system');

        $uncleId = isset($_SESSION['uncle_id']) ? (int) $_SESSION['uncle_id'] : null;

        $churchId = getChurchId();



        $vapidPri = defined('VAPID_PRIVATE_KEY') ? VAPID_PRIVATE_KEY : (getenv('VAPID_PRIVATE_KEY') ?: '');

        $vapidPub = defined('VAPID_PUBLIC_KEY') ? VAPID_PUBLIC_KEY : (getenv('VAPID_PUBLIC_KEY') ?: '');

        if (!$vapidPri || !$vapidPub) {

            sendJSON(['success' => false, 'message' => 'VAPID not configured']);

            return;

        }



        $tbl = $conn->query("SHOW TABLES LIKE 'push_subscriptions'")->fetch_assoc();

        if (!$tbl) {

            sendJSON(['success' => false, 'message' => 'No subscriptions table']);

            return;

        }



        if ($target === 'self' && $uncleId) {

            $stmt = $conn->prepare("SELECT endpoint,p256dh,auth FROM push_subscriptions WHERE uncle_id=? LIMIT 10");

            $stmt->bind_param('i', $uncleId);

        } else {

            $stmt = $conn->prepare("SELECT endpoint,p256dh,auth FROM push_subscriptions WHERE church_id=? LIMIT 50");

            $stmt->bind_param('i', $churchId);

        }

        $stmt->execute();

        $subs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);



        $payload = json_encode([

            'title' => $title,

            'body' => $body,

            'url' => $url,

            'notifType' => $notifType,

            'icon' => '/logo.png',

            'badge' => '/badge.png',

        ]);



        $sent = 0;

        foreach ($subs as $sub) {

            if (_pushToEndpoint($sub['endpoint'], $sub['p256dh'], $sub['auth'], $payload, $vapidPri, $vapidPub)) {

                $sent++;

            }

        }

        sendJSON(['success' => true, 'sent' => $sent]);

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



// Low-level Web Push delivery using VAPID + ece content encoding

function _pushToEndpoint($endpoint, $p256dh, $auth, $payload, $vapidPri, $vapidPub)

{

    try {

        // Use minishlink/web-push if composer is available, otherwise use curl directly

        if (class_exists('\\Minishlink\\WebPush\\WebPush')) {

            $webPush = new \Minishlink\WebPush\WebPush([

                'VAPID' => [

                    'subject' => 'mailto:admin@sunday-school.online',

                    'publicKey' => $vapidPub,

                    'privateKey' => $vapidPri,

                ]

            ]);

            $sub = \Minishlink\WebPush\Subscription::create([

                'endpoint' => $endpoint,

                'keys' => ['p256dh' => $p256dh, 'auth' => $auth],

            ]);

            $webPush->queueNotification($sub, $payload);

            foreach ($webPush->flush() as $r) {

                if (!$r->isSuccess())

                    return false;

            }

            return true;

        }

        // Fallback: call our own send-via-Node helper if available

        // (Set PUSH_HELPER_URL in config to a small Node.js micro-service)

        $helperUrl = defined('PUSH_HELPER_URL') ? PUSH_HELPER_URL : getenv('PUSH_HELPER_URL');

        if ($helperUrl) {

            $data = json_encode(compact('endpoint', 'p256dh', 'auth', 'payload', 'vapidPri', 'vapidPub'));

            $ch = curl_init($helperUrl);

            curl_setopt_array($ch, [

                CURLOPT_POST => 1,

                CURLOPT_POSTFIELDS => $data,

                CURLOPT_RETURNTRANSFER => 1,

                CURLOPT_TIMEOUT => 8,

                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],

            ]);

            $res = curl_exec($ch);

            curl_close($ch);

            $r = json_decode($res, true);

            return ($r['success'] ?? false);

        }

        return false;

    } catch (Exception $e) {

        error_log("_pushToEndpoint error: " . $e->getMessage());

        return false;

    }

}



// ════════════════════════════════════════════════════════════════

// OPEN QUESTION GRADING

// ════════════════════════════════════════════════════════════════



function getPendingOpenSubmissions()

{

    try {

        $conn = getDBConnection();

        $churchId = getChurchId();

        $taskId = (int) ($_POST['task_id'] ?? 0);



        // Ensure columns exist

        $conn->query("ALTER TABLE task_submissions ADD COLUMN IF NOT EXISTS is_graded TINYINT(1) NOT NULL DEFAULT 0");

        $conn->query("ALTER TABLE task_submissions ADD COLUMN IF NOT EXISTS open_scores LONGTEXT DEFAULT NULL");

        $conn->query("ALTER TABLE task_questions  ADD COLUMN IF NOT EXISTS question_type ENUM('mcq','open') NOT NULL DEFAULT 'mcq' AFTER id");



        $where = "ts.church_id = ? AND ts.is_graded = 0";

        $params = [$churchId];

        $types = 'i';



        // Check if task has any open questions

        if ($taskId) {

            $where .= " AND ts.task_id = ?";

            $params[] = $taskId;

            $types .= 'i';

        }



        $stmt = $conn->prepare("

            SELECT ts.id, ts.task_id, ts.student_id, ts.answers, ts.submitted_at,

                   s.name AS student_name, t.title AS task_title,

                   t.total_degree, t.coupon_matrix

            FROM task_submissions ts

            JOIN students s ON s.id = ts.student_id

            JOIN tasks t ON t.id = ts.task_id

            WHERE $where

            ORDER BY ts.submitted_at DESC

            LIMIT 100

        ");

        $stmt->bind_param($types, ...$params);

        $stmt->execute();

        $subs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);



        // Attach open questions for each unique task

        $taskQCache = [];

        foreach ($subs as &$sub) {

            $tid = $sub['task_id'];

            if (!isset($taskQCache[$tid])) {

                $qStmt = $conn->prepare("SELECT id, question_text, degree FROM task_questions WHERE task_id=? AND question_type='open' ORDER BY sort_order");

                $qStmt->bind_param('i', $tid);

                $qStmt->execute();

                $taskQCache[$tid] = $qStmt->get_result()->fetch_all(MYSQLI_ASSOC);

            }

            $sub['open_questions'] = $taskQCache[$tid];

        }

        unset($sub);



        sendJSON(['success' => true, 'submissions' => $subs]);

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



function gradeOpenAnswer()

{

    try {

        $conn = getDBConnection();

        $churchId = getChurchId();

        $uncleId = (int) ($_SESSION['uncle_id'] ?? 0);

        $subId = (int) ($_POST['submission_id'] ?? 0);

        $scoresJson = $_POST['scores'] ?? '{}'; // {question_id: score}

        $notesJson = $_POST['notes'] ?? '{}'; // {question_id: "note text"}



        if (!$subId) {

            sendJSON(['success' => false, 'message' => 'submission_id مطلوب']);

            return;

        }



        // Ensure columns exist

        $conn->query("ALTER TABLE task_submissions ADD COLUMN IF NOT EXISTS is_graded TINYINT(1) NOT NULL DEFAULT 0");

        $conn->query("ALTER TABLE task_submissions ADD COLUMN IF NOT EXISTS open_scores LONGTEXT DEFAULT NULL");

        $conn->query("ALTER TABLE task_submissions ADD COLUMN IF NOT EXISTS score INT NOT NULL DEFAULT 0");

        $conn->query("ALTER TABLE task_submissions ADD COLUMN IF NOT EXISTS coupons_awarded INT NOT NULL DEFAULT 0");

        $conn->query("ALTER TABLE task_submissions ADD COLUMN IF NOT EXISTS graded_by_uncle_id INT DEFAULT NULL");

        $conn->query("ALTER TABLE task_submissions ADD COLUMN IF NOT EXISTS graded_at DATETIME DEFAULT NULL");

        $conn->query("ALTER TABLE task_submissions ADD COLUMN IF NOT EXISTS correction_notes LONGTEXT DEFAULT NULL");



        $scores = json_decode($scoresJson, true) ?: [];

        $notes = json_decode($notesJson, true) ?: [];

        // Filter out empty notes

        $notes = array_filter($notes, function($v) { return is_string($v) && trim($v) !== ''; });



        // Load submission + task

        $subStmt = $conn->prepare("
            SELECT ts.*, t.total_degree, t.coupon_matrix, t.class_id, t.title AS task_title, s.name AS student_name, s.class AS student_class
            FROM task_submissions ts 
            JOIN tasks t ON t.id=ts.task_id 
            LEFT JOIN students s ON s.id=ts.student_id
            WHERE ts.id=? AND ts.church_id=?
        ");

        $subStmt->bind_param('ii', $subId, $churchId);

        $subStmt->execute();

        $sub = $subStmt->get_result()->fetch_assoc();

        if (!$sub) {

            sendJSON(['success' => false, 'message' => 'السجل غير موجود']);

            return;

        }



        // Calculate MCQ score from existing answers

        $answers = json_decode($sub['answers'] ?? '{}', true) ?: [];

        $qStmt = $conn->prepare("SELECT id, question_type, correct_index, degree FROM task_questions WHERE task_id=?");

        $qStmt->bind_param('i', $sub['task_id']);

        $qStmt->execute();

        $questions = $qStmt->get_result()->fetch_all(MYSQLI_ASSOC);



        $mcqScore = 0;

        $openScore = 0;

        foreach ($questions as $q) {

            if ($q['question_type'] === 'open' || $q['question_type'] === null) {

                $openScore += (int) ($scores[$q['id']] ?? $scores[(string) $q['id']] ?? 0);

            } else {

                // tf and mcq — correct_index should be set; skip if null to be safe

                if ($q['correct_index'] === null)

                    continue;

                $given = $answers[$q['id']] ?? $answers[(string) $q['id']] ?? null;

                if ($given !== null && (int) $given === (int) $q['correct_index']) {

                    $mcqScore += (int) $q['degree'];

                }

            }

        }

        $totalScore = $mcqScore + $openScore;



        // Compute coupons from matrix

        $pct = $sub['total_degree'] > 0 ? ($totalScore / $sub['total_degree'] * 100) : 0;

        $matrix = json_decode($sub['coupon_matrix'] ?? '[]', true) ?: [];

        $coupons = 0;

        foreach ($matrix as $tier) {

            if ($pct >= (float) $tier['from'] && $pct <= (float) $tier['to']) {

                $coupons = (int) $tier['val'];

                break;

            }

        }



        $conn->begin_transaction();



        // ── Coupon diff: only the DIFFERENCE applied to task_coupons only ──

        $prevCoupons = (int) ($sub['coupons_awarded'] ?? 0);

        $couponDiff = $coupons - $prevCoupons; // positive = add more, negative = reduce, 0 = no change



        // Update submission with full recalculated score (replaces old score entirely)

        $openJson = json_encode($scores, JSON_UNESCAPED_UNICODE);

        $corrNotesJson = !empty($notes) ? json_encode($notes, JSON_UNESCAPED_UNICODE) : null;

        $upd = $conn->prepare("UPDATE task_submissions SET score=?, open_scores=?, correction_notes=?, coupons_awarded=?, is_graded=1, graded_by_uncle_id=?, graded_at=NOW() WHERE id=?");

        $upd->bind_param('issiii', $totalScore, $openJson, $corrNotesJson, $coupons, $uncleId, $subId);

        $upd->execute();



        // Apply coupon diff to task_coupons AND recalculate total coupons

        if ($couponDiff !== 0) {

            $stuStmt = $conn->prepare("SELECT name, coupons, task_coupons, attendance_coupons, commitment_coupons FROM students WHERE id=? LIMIT 1");

            $stuStmt->bind_param('i', $sub['student_id']);

            $stuStmt->execute();

            $stu = $stuStmt->get_result()->fetch_assoc();

            if ($stu) {

                $newTask = max(0, (int) $stu['task_coupons'] + $couponDiff);

                $newTotal = $newTask + (int) $stu['attendance_coupons'] + (int) $stu['commitment_coupons'];

                $conn->query("UPDATE students SET task_coupons={$newTask}, coupons={$newTotal} WHERE id={$sub['student_id']}");

                // Log

                $sign = $couponDiff > 0 ? "إضافة {$couponDiff}" : "خصم " . abs($couponDiff);

                $reason = "تصحيح مهمة #{$sub['task_id']}: {$sign} كوبون";

                $log = $conn->prepare("INSERT INTO coupon_logs (student_id, uncle_id, old_count, new_count, change_amount, change_type, reason) VALUES (?,?,?,?,?,'task',?)");

                $log->bind_param('iiiiss', $sub['student_id'], $uncleId, $stu['task_coupons'], $newTask, $couponDiff, $reason);

                $log->execute();



                // ► AUDIT

                auditCouponChange($sub['student_id'], $stu['name'] ?? '', (int) $stu['coupons'], $newTotal, $reason);

            }

        }

        // Insert notification for the student
        $studentName = $sub['student_name'] ?? '';
        $studentClass = $sub['student_class'] ?? '';
        $taskTitle = $sub['task_title'] ?? '';

        if ($studentName !== '') {
            $annText = "تم تصحيح المهمة: \"{$taskTitle}\" وحصلت على {$coupons} من الكوبونات!";
            $annStmt = $conn->prepare("INSERT INTO announcements (church_id, type, text, link, class, student_names, is_active, created_at) VALUES (?, 'task', ?, '', ?, ?, 1, NOW())");
            $annStmt->bind_param('isss', $churchId, $annText, $studentClass, $studentName);
            $annStmt->execute();
        }

        $conn->commit();



        // Push notification

        pushNotification(

            $conn,

            $churchId,

            'task_submission',

            'تم تصحيح إجابة مفتوحة',

            "تم تصحيح إجابة الطفل بدرجة {$totalScore} من {$sub['total_degree']}",

            'task',

            $sub['task_id']

        );



        sendJSON(['success' => true, 'score' => $totalScore, 'coupons' => $coupons, 'coupon_diff' => $couponDiff, 'percentage' => round($pct, 1)]);

    } catch (Exception $e) {

        if (isset($conn))

            $conn->rollback();

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



// ══════════════════════════════════════════════════════════════

// UNCLE ATTENDANCE FUNCTIONS

// ══════════════════════════════════════════════════════════════



function ensureUncleAttendanceTable($conn)

{

    $conn->query("

        CREATE TABLE IF NOT EXISTS `uncle_attendance` (

          `id` int(11) NOT NULL AUTO_INCREMENT,

          `uncle_id` int(11) NOT NULL,

          `church_id` int(11) NOT NULL,

          `attendance_date` date NOT NULL,

          `status` enum('present','absent') NOT NULL DEFAULT 'present',

          `recorded_by` int(11) DEFAULT NULL,

          `created_at` timestamp NULL DEFAULT current_timestamp(),

          `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),

          PRIMARY KEY (`id`),

          UNIQUE KEY `uncle_date_church` (`uncle_id`,`attendance_date`,`church_id`),

          KEY `church_date` (`church_id`,`attendance_date`)

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci

    ");

}



function submitUncleAttendance()

{

    try {

        $churchId = getChurchId();

        $conn = getDBConnection();

        ensureUncleAttendanceTable($conn);



        $date = sanitize($_POST['date'] ?? date('Y-m-d'));

        // Normalize date format

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $m)) {

            $date = $m[3] . '-' . $m[2] . '-' . $m[1];

        }



        $attendanceData = json_decode($_POST['attendanceData'] ?? '[]', true);

        if (!is_array($attendanceData) || empty($attendanceData)) {

            sendJSON(['success' => false, 'message' => 'بيانات الحضور فارغة']);

        }



        $recordedBy = $_SESSION['church_id'] ?? null;

        $inserted = 0;

        $updated = 0;



        $conn->begin_transaction();

        foreach ($attendanceData as $row) {

            $uncleId = intval($row['uncle_id']);

            $status = in_array($row['status'], ['present', 'absent']) ? $row['status'] : 'present';



            // Upsert

            $check = $conn->prepare("SELECT id FROM uncle_attendance WHERE uncle_id=? AND attendance_date=? AND church_id=?");

            $check->bind_param('isi', $uncleId, $date, $churchId);

            $check->execute();

            $existing = $check->get_result()->fetch_assoc();



            if ($existing) {

                $upd = $conn->prepare("UPDATE uncle_attendance SET status=?, recorded_by=?, updated_at=NOW() WHERE id=?");

                $upd->bind_param('sii', $status, $recordedBy, $existing['id']);

                $upd->execute();

                $updated++;

            } else {

                $ins = $conn->prepare("INSERT INTO uncle_attendance (uncle_id, church_id, attendance_date, status, recorded_by) VALUES (?,?,?,?,?)");

                $ins->bind_param('iissi', $uncleId, $churchId, $date, $status, $recordedBy);

                $ins->execute();

                $inserted++;

            }

        }

        $conn->commit();

        sendJSON(['success' => true, 'inserted' => $inserted, 'updated' => $updated, 'date' => $date]);

    } catch (Exception $e) {

        if (isset($conn))

            $conn->rollback();

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



function getUncleAttendanceByDate()

{

    try {

        $churchId = getChurchId();

        $conn = getDBConnection();

        ensureUncleAttendanceTable($conn);



        $rawDate = $_POST['date'] ?? date('Y-m-d');

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $rawDate, $m)) {

            $date = $m[3] . '-' . $m[2] . '-' . $m[1];

        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {

            $date = $rawDate;

        } else {

            $date = date('Y-m-d');

        }



        $stmt = $conn->prepare("

            SELECT ua.id, ua.uncle_id, ua.attendance_date, ua.status,

                   u.name AS uncle_name, u.role, u.image_url,

                   rec.name AS recorded_by_name

            FROM uncle_attendance ua

            JOIN uncles u ON ua.uncle_id = u.id

            LEFT JOIN uncles rec ON ua.recorded_by = rec.id

            WHERE ua.church_id = ? AND ua.attendance_date = ?

            ORDER BY u.name

        ");

        $stmt->bind_param('is', $churchId, $date);

        $stmt->execute();

        $result = $stmt->get_result();



        $attendance = [];

        while ($row = $result->fetch_assoc()) {

            $attendance[] = [

                'id' => $row['id'],

                'uncle_id' => $row['uncle_id'],

                'uncle_name' => $row['uncle_name'],

                'role' => $row['role'],

                'image_url' => $row['image_url'],

                'status' => $row['status'],

                'status_text' => $row['status'] === 'present' ? 'حاضر' : 'غائب',

                'recorded_by' => $row['recorded_by_name'] ?? 'النظام',

                'date' => $date,

            ];

        }



        sendJSON(['success' => true, 'attendance' => $attendance, 'date' => $date, 'count' => count($attendance)]);

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



function getUncleAttendanceReport()

{

    try {

        $churchId = getChurchId();

        if (!$churchId) {

            sendJSON(['success' => false, 'message' => 'معرف الكنيسة غير صالح — أعد تسجيل الدخول']);

            return;

        }

        $conn = getDBConnection();

        ensureUncleAttendanceTable($conn);



        $year = intval($_POST['year'] ?? date('Y'));

        if ($year < 2000 || $year > 2100) {

            $year = (int) date('Y');

        }



        // Get all uncles for this church (uncles table has deleted, not is_active)

        $uncleStmt = $conn->prepare("

            SELECT id, name, role, image_url

            FROM uncles

            WHERE church_id = ? AND (deleted IS NULL OR deleted = 0)

            ORDER BY name

        ");

        $uncleStmt->bind_param('i', $churchId);

        if (!$uncleStmt->execute()) {

            sendJSON(['success' => false, 'message' => 'خطأ في جلب الخُدام: ' . $uncleStmt->error]);

            return;

        }

        $uncles = $uncleStmt->get_result()->fetch_all(MYSQLI_ASSOC);



        // Get all uncle attendance for this year

        $attStmt = $conn->prepare("

            SELECT ua.uncle_id, ua.attendance_date, ua.status

            FROM uncle_attendance ua

            WHERE ua.church_id=? AND YEAR(ua.attendance_date)=?

            ORDER BY ua.attendance_date ASC

        ");

        $attStmt->bind_param('ii', $churchId, $year);

        $attStmt->execute();

        $allAtt = $attStmt->get_result()->fetch_all(MYSQLI_ASSOC);



        // Get distinct attendance dates (Fridays with records)

        $dateStmt = $conn->prepare("

            SELECT DISTINCT attendance_date

            FROM uncle_attendance

            WHERE church_id=? AND YEAR(attendance_date)=?

            ORDER BY attendance_date ASC

        ");

        $dateStmt->bind_param('ii', $churchId, $year);

        $dateStmt->execute();

        $dates = array_column($dateStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'attendance_date');



        // Build per-uncle stats

        $attMap = [];

        foreach ($allAtt as $a) {

            $attMap[$a['uncle_id']][$a['attendance_date']] = $a['status'];

        }



        // Count present days per date (for day ranking)

        $dayPresent = [];

        foreach ($allAtt as $a) {

            if ($a['status'] === 'present') {

                $dayPresent[$a['attendance_date']] = ($dayPresent[$a['attendance_date']] ?? 0) + 1;

            }

        }



        // Sort dates by present count descending

        arsort($dayPresent);



        $uncleReport = [];

        foreach ($uncles as $u) {

            $uid = $u['id'];

            $presentDays = [];

            $absentDays = [];

            foreach ($dates as $d) {

                $s = $attMap[$uid][$d] ?? null;

                if ($s === 'present')

                    $presentDays[] = $d;

                elseif ($s === 'absent')

                    $absentDays[] = $d;

            }

            $total = count($dates);

            $pct = $total > 0 ? round(count($presentDays) / $total * 100) : 0;

            $uncleReport[] = [

                'uncle_id' => $uid,

                'uncle_name' => $u['name'],

                'role' => $u['role'],

                'image_url' => $u['image_url'],

                'present_count' => count($presentDays),

                'absent_count' => count($absentDays),

                'total_days' => $total,

                'percentage' => $pct,

                'present_dates' => $presentDays,

                'absent_dates' => $absentDays,

            ];

        }



        // Sort uncles by present_count descending

        usort($uncleReport, fn($a, $b) => $b['present_count'] - $a['present_count']);



        // Day rankings sorted by present count high→low

        $dayRanking = [];

        foreach ($dayPresent as $d => $cnt) {

            $dayRanking[] = [

                'date' => $d,

                'present_count' => $cnt,

                'absent_count' => count(array_filter($allAtt, fn($a) => $a['attendance_date'] === $d && $a['status'] === 'absent')),

                'total_uncles' => count($uncles),

            ];

        }



        sendJSON([

            'success' => true,

            'uncle_report' => $uncleReport,

            'day_ranking' => $dayRanking,

            'dates' => $dates,

            'year' => $year,

            'total_uncles' => count($uncles),

        ]);

    } catch (Exception $e) {

        error_log('getUncleAttendanceReport error: ' . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تقرير الخُدام: ' . $e->getMessage()]);

    }

}



function toggleUncleAttendance()

{

    try {

        $churchId = getChurchId();

        $conn = getDBConnection();

        ensureUncleAttendanceTable($conn);



        $id = intval($_POST['id'] ?? 0);

        $status = sanitize($_POST['status'] ?? 'present');

        if (!in_array($status, ['present', 'absent']))

            sendJSON(['success' => false, 'message' => 'حالة غير صحيحة']);



        $stmt = $conn->prepare("UPDATE uncle_attendance SET status=?, updated_at=NOW() WHERE id=? AND church_id=?");

        $stmt->bind_param('sii', $status, $id, $churchId);

        $stmt->execute();

        sendJSON(['success' => true]);

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



function deleteUncleAttendance()

{

    try {

        $churchId = getChurchId();

        $conn = getDBConnection();

        ensureUncleAttendanceTable($conn);



        $id = intval($_POST['id'] ?? 0);

        $stmt = $conn->prepare("DELETE FROM uncle_attendance WHERE id=? AND church_id=?");

        $stmt->bind_param('ii', $id, $churchId);

        $stmt->execute();

        sendJSON(['success' => true]);

    } catch (Exception $e) {

        sendJSON(['success' => false, 'message' => $e->getMessage()]);

    }

}



function getCustomFieldTemplates()

{

    try {

        $conn = getDBConnection();

        $conn->query("CREATE TABLE IF NOT EXISTS `custom_field_templates` (

            `id` INT AUTO_INCREMENT PRIMARY KEY,

            `name` VARCHAR(255) NOT NULL,

            `custom_fields` TEXT NOT NULL,

            `custom_field_icons` TEXT NOT NULL,

            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");



        $check = $conn->query("SELECT COUNT(*) as count FROM `custom_field_templates`")->fetch_assoc();

        if (intval($check['count']) === 0) {

            $defaultName = "بيت الاخوه بالسويس";

            $defaultFields = "السكن";

            $defaultIcons = json_encode([

                "السكن" => [

                    "name" => "السكن",

                    "icon" => "fas fa-home",

                    "type" => "sub_group",

                    "choices" => ["المبنى", "فيلا", "فيلا الفرح"],

                    "sub_fields" => [

                        "المبنى" => [

                            [

                                "name" => "الغرفه",

                                "icon" => "fas fa-door-closed",

                                "type" => "choices",

                                "choices" => ["1", "2", "3", "4", "5", "6", "7", "8", "9", "10", "11", "12", "13", "14", "15", "16", "17", "18", "19", "20"]

                            ]

                        ],

                        "فيلا" => [

                            [

                                "name" => "الغرفه",

                                "icon" => "fas fa-door-closed",

                                "type" => "choices",

                                "choices" => ["1", "2", "3", "4", "5", "6", "7", "8", "9", "10", "11", "12", "13", "14", "15"]

                            ]

                        ],

                        "فيلا الفرح" => [

                            [

                                "name" => "الغرفه",

                                "icon" => "fas fa-door-closed",

                                "type" => "choices",

                                "choices" => ["1", "2", "3", "4", "5"]

                            ]

                        ]

                    ]

                ]

            ], JSON_UNESCAPED_UNICODE);



            $seedStmt = $conn->prepare("INSERT INTO `custom_field_templates` (name, custom_fields, custom_field_icons) VALUES (?, ?, ?)");

            $seedStmt->bind_param("sss", $defaultName, $defaultFields, $defaultIcons);

            $seedStmt->execute();

        }



        $res = $conn->query("SELECT * FROM `custom_field_templates` ORDER BY id DESC");

        $templates = [];

        while ($row = $res->fetch_assoc()) {

            $templates[] = [

                'id' => intval($row['id']),

                'name' => $row['name'],

                'custom_fields' => $row['custom_fields'],

                'custom_field_icons' => json_decode($row['custom_field_icons'], true)

            ];

        }



        sendJSON(['success' => true, 'templates' => $templates]);

    } catch (Exception $e) {

        error_log("getCustomFieldTemplates error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في جلب القوالب: ' . $e->getMessage()]);

    }

}



function addCustomFieldTemplate()

{

    try {

        $role = $_SESSION['uncle_role'] ?? 'uncle';

        if ($role !== 'developer') {

            sendJSON(['success' => false, 'message' => 'غير مصرح للمطورين فقط']);

            return;

        }



        $name = trim($_POST['name'] ?? '');

        $fields = trim($_POST['custom_fields'] ?? '');

        $iconsRaw = $_POST['custom_field_icons'] ?? '';



        if (empty($name)) {

            sendJSON(['success' => false, 'message' => 'اسم القالب مطلوب']);

            return;

        }



        $iconsDecoded = json_decode($iconsRaw, true);

        if (!is_array($iconsDecoded)) {

            sendJSON(['success' => false, 'message' => 'تنسيق الايقونات غير صالح']);

            return;

        }



        $conn = getDBConnection();

        $conn->query("CREATE TABLE IF NOT EXISTS `custom_field_templates` (

            `id` INT AUTO_INCREMENT PRIMARY KEY,

            `name` VARCHAR(255) NOT NULL,

            `custom_fields` TEXT NOT NULL,

            `custom_field_icons` TEXT NOT NULL,

            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");



        $stmt = $conn->prepare("INSERT INTO `custom_field_templates` (name, custom_fields, custom_field_icons) VALUES (?, ?, ?)");

        $stmt->bind_param("sss", $name, $fields, $iconsRaw);

        if ($stmt->execute()) {

            sendJSON(['success' => true, 'message' => 'تم حفظ القالب بنجاح', 'id' => $conn->insert_id]);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل حفظ القالب: ' . $stmt->error]);

        }

    } catch (Exception $e) {

        error_log("addCustomFieldTemplate error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في إضافة القالب: ' . $e->getMessage()]);

    }

}



function deleteCustomFieldTemplate()

{

    try {

        $role = $_SESSION['uncle_role'] ?? 'uncle';

        if ($role !== 'developer') {

            sendJSON(['success' => false, 'message' => 'غير مصرح للمطورين فقط']);

            return;

        }



        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {

            sendJSON(['success' => false, 'message' => 'معرف القالب غير صالح']);

            return;

        }



        $conn = getDBConnection();

        $stmt = $conn->prepare("DELETE FROM `custom_field_templates` WHERE id = ?");

        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {

            sendJSON(['success' => true, 'message' => 'تم حذف القالب بنجاح']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل حذف القالب: ' . $stmt->error]);

        }

    } catch (Exception $e) {

        error_log("deleteCustomFieldTemplate error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في حذف القالب: ' . $e->getMessage()]);

    }

}



function ensureGuestsTable($conn)

{

    // Step 1: Create guests table if it doesn't exist

    $conn->query("

        CREATE TABLE IF NOT EXISTS `guests` (

            `id` INT AUTO_INCREMENT PRIMARY KEY,

            `church_id` INT NOT NULL COMMENT 'Which church added this guest',

            `name` VARCHAR(255) NOT NULL COMMENT 'Full name of the guest child',

            `phone` VARCHAR(20) DEFAULT NULL COMMENT 'Phone number (guardian/child)',

            `guardian_name` VARCHAR(255) DEFAULT NULL COMMENT 'Name of parent/guardian',

            `class` VARCHAR(100) DEFAULT NULL COMMENT 'Class/grade if known',

            `gender` ENUM('male','female') DEFAULT NULL,

            `notes` TEXT DEFAULT NULL,

            `created_by` INT DEFAULT NULL COMMENT 'Uncle ID who added the guest',

            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            INDEX idx_guests_church (`church_id`),

            INDEX idx_guests_name (`name`)

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci

    ");



    // Step 2: Add registration_type to trip_registrations if missing

    $res = $conn->query("SHOW COLUMNS FROM `trip_registrations` LIKE 'registration_type'");

    if ($res && $res->num_rows === 0) {

        $conn->query("ALTER TABLE `trip_registrations`

            ADD COLUMN `registration_type` ENUM('student', 'other_church_student', 'guest') DEFAULT 'student',

            MODIFY `student_id` INT DEFAULT NULL");

        $conn->query("CREATE INDEX idx_trip_reg_type ON trip_registrations(registration_type)");

    }



    // Step 3: Add guest_id column to trip_registrations if missing

    $res2 = $conn->query("SHOW COLUMNS FROM `trip_registrations` LIKE 'guest_id'");

    if ($res2 && $res2->num_rows === 0) {

        $conn->query("ALTER TABLE `trip_registrations` ADD COLUMN `guest_id` INT DEFAULT NULL");

        $conn->query("CREATE INDEX idx_trip_reg_guest ON trip_registrations(guest_id)");



        // Migrate any existing inline guest data to the new guests table

        $hasGuestName = $conn->query("SHOW COLUMNS FROM `trip_registrations` LIKE 'guest_name'");

        if ($hasGuestName && $hasGuestName->num_rows > 0) {

            // Insert existing guests into guests table

            $conn->query("

                INSERT INTO `guests` (`church_id`, `name`, `phone`, `guardian_name`, `class`, `gender`, `notes`, `created_at`)

                SELECT DISTINCT t.church_id, tr.guest_name, tr.guest_phone, tr.guest_guardian_name, tr.guest_class, tr.guest_gender, tr.notes, tr.created_at

                FROM `trip_registrations` tr

                JOIN `trips` t ON tr.trip_id = t.id

                WHERE tr.registration_type = 'guest' AND tr.guest_name IS NOT NULL

            ");



            // Link trip_registrations to new guests

            $conn->query("

                UPDATE `trip_registrations` tr

                JOIN `trips` t ON tr.trip_id = t.id

                JOIN `guests` g ON g.name = tr.guest_name AND g.church_id = t.church_id AND COALESCE(g.phone, '') = COALESCE(tr.guest_phone, '')

                SET tr.guest_id = g.id

                WHERE tr.registration_type = 'guest' AND tr.guest_id IS NULL AND tr.guest_name IS NOT NULL

            ");



            // Drop old inline columns

            $conn->query("ALTER TABLE `trip_registrations`

                DROP COLUMN `guest_name`,

                DROP COLUMN `guest_phone`,

                DROP COLUMN `guest_guardian_name`,

                DROP COLUMN `guest_class`,

                DROP COLUMN `guest_gender`

            ");



            // Drop display_name if it exists

            $hasDisplayName = $conn->query("SHOW COLUMNS FROM `trip_registrations` LIKE 'display_name'");

            if ($hasDisplayName && $hasDisplayName->num_rows > 0) {

                $conn->query("ALTER TABLE `trip_registrations` DROP COLUMN `display_name`");

            }

        }

    }

}



// Legacy alias for backward compatibility during transition

function ensureGuestTripColumns($conn)

{

    ensureGuestsTable($conn);

}



function searchAllStudents()

{

    try {

        checkAuth();

        $query = trim($_POST['query'] ?? $_GET['query'] ?? '');

        if (mb_strlen($query) < 2) {

            sendJSON(['success' => true, 'students' => []]);

            return;

        }



        $conn = getDBConnection();

        $searchTerm = "%" . $query . "%";



        // Query students across all churches

        $stmt = $conn->prepare("

            SELECT 

                s.id, 

                s.name, 

                s.phone, 

                s.gender, 

                COALESCE(cc.arabic_name, gc.arabic_name, s.class) as class,

                c.id as church_id,

                c.church_name

            FROM students s

            LEFT JOIN church_classes cc ON s.class_id = cc.id AND cc.church_id = s.church_id

            LEFT JOIN classes gc ON s.class_id = gc.id

            LEFT JOIN churches c ON s.church_id = c.id

            WHERE (s.name LIKE ? OR s.phone LIKE ?) AND COALESCE(s.enrollment_status, 'active') = 'active'

            ORDER BY s.name ASC

            LIMIT 50

        ");

        $stmt->bind_param("ss", $searchTerm, $searchTerm);

        $stmt->execute();

        $result = $stmt->get_result();



        $students = [];

        while ($row = $result->fetch_assoc()) {

            $students[] = $row;

        }



        sendJSON(['success' => true, 'students' => $students, 'data' => $students]);

    } catch (Exception $e) {

        error_log("searchAllStudents error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في البحث: ' . $e->getMessage()]);

    }

}



function getGuests()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $conn = getDBConnection();

        ensureGuestsTable($conn);



        // Select all guests for this church with their trip registration info

        $stmt = $conn->prepare("

            SELECT 

                g.*,

                u.name as created_by_name,

                (SELECT COUNT(*) FROM trip_registrations tr WHERE tr.guest_id = g.id AND tr.cancelled = 0) as trip_count,

                (SELECT GROUP_CONCAT(DISTINCT t.title SEPARATOR '، ') FROM trip_registrations tr JOIN trips t ON tr.trip_id = t.id WHERE tr.guest_id = g.id AND tr.cancelled = 0) as trip_names

            FROM guests g

            LEFT JOIN uncles u ON g.created_by = u.id

            WHERE g.church_id = ?

            ORDER BY g.created_at DESC

        ");

        $stmt->bind_param("i", $churchId);

        $stmt->execute();

        $res = $stmt->get_result();

        $guests = [];

        while ($row = $res->fetch_assoc()) {

            $guests[] = $row;

        }

        sendJSON(['success' => true, 'data' => $guests]);

    } catch (Exception $e) {

        error_log("getGuests error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحميل الزوار: ' . $e->getMessage()]);

    }

}



function addGuest()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $uncleId = getUncleId();

        $conn = getDBConnection();

        ensureGuestsTable($conn);



        $name = sanitize($_POST['name'] ?? '');

        $phone = sanitize($_POST['phone'] ?? '');

        $guardianName = sanitize($_POST['guardian_name'] ?? '');

        $guestClass = sanitize($_POST['class'] ?? '');

        $gender = sanitize($_POST['gender'] ?? '');

        $notes = sanitize($_POST['notes'] ?? '');



        if (empty($name)) {

            sendJSON(['success' => false, 'message' => 'اسم الزائر مطلوب']);

            return;

        }



        if (!in_array($gender, ['male', 'female', ''])) {

            $gender = null;

        }

        if ($gender === '') $gender = null;

        if ($phone === '') $phone = null;

        if ($guardianName === '') $guardianName = null;

        if ($guestClass === '') $guestClass = null;

        if ($notes === '') $notes = null;



        $stmt = $conn->prepare("

            INSERT INTO guests (church_id, name, phone, guardian_name, `class`, gender, notes, created_by)

            VALUES (?, ?, ?, ?, ?, ?, ?, ?)

        ");

        $stmt->bind_param("issssssi", $churchId, $name, $phone, $guardianName, $guestClass, $gender, $notes, $uncleId);



        if ($stmt->execute()) {

            $guestId = $conn->insert_id;

            sendJSON(['success' => true, 'guest_id' => $guestId, 'message' => 'تم إضافة الزائر بنجاح']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في إضافة الزائر: ' . $stmt->error]);

        }

    } catch (Exception $e) {

        error_log("addGuest error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في إضافة الزائر: ' . $e->getMessage()]);

    }

}



function updateGuest()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $conn = getDBConnection();

        ensureGuestsTable($conn);



        $guestId = intval($_POST['guest_id'] ?? 0);

        if ($guestId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف الزائر مطلوب']);

            return;

        }



        // Verify guest belongs to this church

        $check = $conn->prepare("SELECT id FROM guests WHERE id = ? AND church_id = ?");

        $check->bind_param("ii", $guestId, $churchId);

        $check->execute();

        if ($check->get_result()->num_rows === 0) {

            sendJSON(['success' => false, 'message' => 'الزائر غير موجود']);

            return;

        }



        $name = sanitize($_POST['name'] ?? '');

        $phone = sanitize($_POST['phone'] ?? '');

        $guardianName = sanitize($_POST['guardian_name'] ?? '');

        $guestClass = sanitize($_POST['class'] ?? '');

        $gender = sanitize($_POST['gender'] ?? '');

        $notes = sanitize($_POST['notes'] ?? '');



        if (empty($name)) {

            sendJSON(['success' => false, 'message' => 'اسم الزائر مطلوب']);

            return;

        }



        if (!in_array($gender, ['male', 'female', ''])) $gender = null;

        if ($gender === '') $gender = null;

        if ($phone === '') $phone = null;

        if ($guardianName === '') $guardianName = null;

        if ($guestClass === '') $guestClass = null;

        if ($notes === '') $notes = null;



        $stmt = $conn->prepare("

            UPDATE guests SET name = ?, phone = ?, guardian_name = ?, `class` = ?, gender = ?, notes = ?

            WHERE id = ? AND church_id = ?

        ");

        $stmt->bind_param("ssssssii", $name, $phone, $guardianName, $guestClass, $gender, $notes, $guestId, $churchId);



        if ($stmt->execute()) {

            sendJSON(['success' => true, 'message' => 'تم تحديث بيانات الزائر بنجاح']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في تحديث الزائر: ' . $stmt->error]);

        }

    } catch (Exception $e) {

        error_log("updateGuest error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في تحديث الزائر: ' . $e->getMessage()]);

    }

}



function deleteGuest()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $conn = getDBConnection();

        ensureGuestsTable($conn);



        $guestId = intval($_POST['guest_id'] ?? 0);

        if ($guestId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف الزائر مطلوب']);

            return;

        }



        // Verify guest belongs to this church

        $check = $conn->prepare("SELECT id FROM guests WHERE id = ? AND church_id = ?");

        $check->bind_param("ii", $guestId, $churchId);

        $check->execute();

        if ($check->get_result()->num_rows === 0) {

            sendJSON(['success' => false, 'message' => 'الزائر غير موجود']);

            return;

        }



        // Cancel any active trip registrations for this guest

        $cancelStmt = $conn->prepare("UPDATE trip_registrations SET cancelled = 1, cancelled_at = NOW() WHERE guest_id = ? AND cancelled = 0");

        $cancelStmt->bind_param("i", $guestId);

        $cancelStmt->execute();



        // Delete the guest record

        $delStmt = $conn->prepare("DELETE FROM guests WHERE id = ? AND church_id = ?");

        $delStmt->bind_param("ii", $guestId, $churchId);



        if ($delStmt->execute()) {

            sendJSON(['success' => true, 'message' => 'تم حذف الزائر بنجاح']);

        } else {

            sendJSON(['success' => false, 'message' => 'فشل في حذف الزائر: ' . $delStmt->error]);

        }

    } catch (Exception $e) {

        error_log("deleteGuest error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في حذف الزائر: ' . $e->getMessage()]);

    }

}



function transferGuestToStudent()

{

    try {

        checkAuth();

        $churchId = getChurchId();

        $conn = getDBConnection();

        ensureGuestsTable($conn);



        $guestId = intval($_POST['guest_id'] ?? 0);

        $classId = intval($_POST['class_id'] ?? 0);

        $customClassName = sanitize($_POST['custom_class_name'] ?? '');



        if ($guestId === 0) {

            sendJSON(['success' => false, 'message' => 'معرف الزائر مطلوب']);

            return;

        }



        // Fetch guest data

        $gstmt = $conn->prepare("SELECT * FROM guests WHERE id = ? AND church_id = ?");

        $gstmt->bind_param("ii", $guestId, $churchId);

        $gstmt->execute();

        $guest = $gstmt->get_result()->fetch_assoc();



        if (!$guest) {

            sendJSON(['success' => false, 'message' => 'الزائر غير موجود']);

            return;

        }



        // Determine the class_id and class name

        $className = '';

        if ($classId > 0) {

            // Check custom church_classes first

            $cs = $conn->prepare("SELECT arabic_name FROM church_classes WHERE id = ? AND church_id = ?");

            $cs->bind_param("ii", $classId, $churchId);

            $cs->execute();

            $cr = $cs->get_result()->fetch_assoc();

            if ($cr) {

                $className = $cr['arabic_name'];

            } else {

                // Try global classes

                $gs = $conn->prepare("SELECT arabic_name FROM classes WHERE id = ?");

                $gs->bind_param("i", $classId);

                $gs->execute();

                $gr = $gs->get_result()->fetch_assoc();

                $className = $gr ? $gr['arabic_name'] : '';

            }

        } elseif (!empty($customClassName)) {

            // "Other" was selected – use custom name, class_id stays 0

            $className = $customClassName;

            $classId = 0;

        } else {

            sendJSON(['success' => false, 'message' => 'يجب اختيار فصل دراسي']);

            return;

        }



        $conn->begin_transaction();



        // Insert into students table

        $name = $guest['name'];

        $gender = $guest['gender'];

        $phone = $guest['phone'];

        $guardianName = $guest['guardian_name'];

        $notes = $guest['notes'];



        $ins = $conn->prepare("

            INSERT INTO students

                (church_id, name, gender, class_id, class, phone, emergency_phone, enrollment_status)

            VALUES (?, ?, ?, ?, ?, ?, ?, 'active')

        ");

        $ins->bind_param(

            "ississs",

            $churchId,

            $name,

            $gender,

            $classId,

            $className,

            $phone,

            $guardianName

        );



        if (!$ins->execute()) {

            $conn->rollback();

            sendJSON(['success' => false, 'message' => 'فشل في إنشاء سجل الطالب: ' . $ins->error]);

            return;

        }



        $newStudentId = $conn->insert_id;



        // Update trip_registrations: link to new student_id and change type

        $upd = $conn->prepare("

            UPDATE trip_registrations

            SET student_id = ?, registration_type = 'student', guest_id = NULL

            WHERE guest_id = ? AND cancelled = 0

        ");

        $upd->bind_param("ii", $newStudentId, $guestId);

        $upd->execute();



        // Delete the guest record (already transferred)

        $del = $conn->prepare("DELETE FROM guests WHERE id = ? AND church_id = ?");

        $del->bind_param("ii", $guestId, $churchId);

        $del->execute();



        $conn->commit();



        sendJSON([

            'success' => true,

            'student_id' => $newStudentId,

            'message' => 'تم تحويل الزائر "' . $name . '" إلى طالب بنجاح'

        ]);

    } catch (Exception $e) {

        if (isset($conn) && $conn->errno) {

            $conn->rollback();

        }

        error_log("transferGuestToStudent error: " . $e->getMessage());

        sendJSON(['success' => false, 'message' => 'خطأ في التحويل: ' . $e->getMessage()]);

    }

}


function getSiblingGroupMembers()
{
    try {
        $studentId = intval($_POST['studentId'] ?? $_GET['studentId'] ?? 0);
        if ($studentId === 0) {
            sendJSON(['success' => false, 'message' => 'معرف الطالب مطلوب']);
            return;
        }
        $conn = getDBConnection();
        // 1. Get the group_id for this student
        $stmt = $conn->prepare("SELECT group_id FROM student_sibling_group_members WHERE student_id = ? LIMIT 1");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        if (!$res) {
            sendJSON(['success' => true, 'siblings' => [], 'message' => 'لا يوجد مجموعة إخوة']);
            return;
        }
        $groupId = $res['group_id'];

        // 2. Fetch all members of this group
        $stmt2 = $conn->prepare("
            SELECT s.id, s.name, s.image_url, s.coupons,
                   COALESCE(cc.arabic_name, cl.arabic_name, s.class) AS class
            FROM student_sibling_group_members ssgm
            INNER JOIN students s ON s.id = ssgm.student_id
            LEFT JOIN church_classes cc ON cc.id = s.class_id AND cc.church_id = s.church_id
            LEFT JOIN classes cl ON cl.id = s.class_id
            WHERE ssgm.group_id = ?
        ");
        $stmt2->bind_param("s", $groupId);
        $stmt2->execute();
        $siblings = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

        sendJSON(['success' => true, 'siblings' => $siblings, 'groupId' => $groupId]);
    } catch (Exception $e) {
        sendJSON(['success' => false, 'message' => $e->getMessage()]);
    }
}

function shareCoupons()
{
    try {
        $conn = getDBConnection();
        $senderId = intval($_POST['senderId'] ?? 0);
        $password = $_POST['password'] ?? '';
        $recipientId = intval($_POST['recipientId'] ?? 0);
        $amount = intval($_POST['amount'] ?? 0);
        $category = sanitize($_POST['category'] ?? 'all'); // 'att', 'com', 'task', or 'all'

        if ($senderId <= 0 || $recipientId <= 0 || $amount <= 0 || empty($password)) {
            sendJSON(['success' => false, 'message' => 'بيانات غير صحيحة أو ناقصة']);
            return;
        }

        if ($senderId === $recipientId) {
            sendJSON(['success' => false, 'message' => 'لا يمكنك إرسال كوبونات لنفسك']);
            return;
        }

        // 1. Verify Sender's password
        $stmt = $conn->prepare("SELECT password_hash, name, coupons, attendance_coupons, commitment_coupons, task_coupons FROM students WHERE id = ?");
        $stmt->bind_param("i", $senderId);
        $stmt->execute();
        $sender = $stmt->get_result()->fetch_assoc();
        if (!$sender) {
            sendJSON(['success' => false, 'message' => 'لم يتم العثور على المرسل']);
            return;
        }

        $storedHash = $sender['password_hash'] ?? '';
        $sha256Hash = hash('sha256', $password);
        $matched = false;
        if (!empty($storedHash)) {
            if ($storedHash === $sha256Hash || password_verify($password, $storedHash)) {
                $matched = true;
            }
        }

        if (!$matched) {
            sendJSON(['success' => false, 'message' => 'كلمة المرور غير صحيحة']);
            return;
        }

        // 2. Fetch Recipient details
        $stmtRec = $conn->prepare("SELECT name, coupons, attendance_coupons, commitment_coupons, task_coupons FROM students WHERE id = ?");
        $stmtRec->bind_param("i", $recipientId);
        $stmtRec->execute();
        $recipient = $stmtRec->get_result()->fetch_assoc();
        if (!$recipient) {
            sendJSON(['success' => false, 'message' => 'لم يتم العثور على المتلقي']);
            return;
        }

        $conn->begin_transaction();

        // 3. Subtract from sender
        $sender_total = intval($sender['coupons']);
        $sender_att = intval($sender['attendance_coupons']);
        $sender_com = intval($sender['commitment_coupons']);
        $sender_tsk = intval($sender['task_coupons']);

        if ($category === 'att') {
            if ($sender_att < $amount) {
                throw new Exception('رصيد كوبونات الحضور غير كافٍ');
            }
            $att_sub = $amount;
            $com_sub = 0;
            $tsk_sub = 0;
        } elseif ($category === 'com') {
            if ($sender_com < $amount) {
                throw new Exception('رصيد كوبونات الالتزام غير كافٍ');
            }
            $att_sub = 0;
            $com_sub = $amount;
            $tsk_sub = 0;
        } elseif ($category === 'task') {
            if ($sender_tsk < $amount) {
                throw new Exception('رصيد كوبونات المهام غير كافٍ');
            }
            $att_sub = 0;
            $com_sub = 0;
            $tsk_sub = $amount;
        } else {
            // 'all' - Greedy subtraction
            if ($sender_total < $amount) {
                throw new Exception('رصيد الكوبونات الإجمالي غير كافٍ');
            }
            $rem = $amount;
            $att_sub = min($sender_att, $rem);
            $rem -= $att_sub;

            $com_sub = 0;
            if ($rem > 0) {
                $com_sub = min($sender_com, $rem);
                $rem -= $com_sub;
            }

            $tsk_sub = 0;
            if ($rem > 0) {
                $tsk_sub = min($sender_tsk, $rem);
                $rem -= $tsk_sub;
            }
            if ($rem > 0) {
                throw new Exception('رصيد الكوبونات الموزعة غير كافٍ');
            }
        }

        $sender_new_total = $sender_total - $amount;

        $updSender = $conn->prepare("UPDATE students SET coupons = ?, attendance_coupons = attendance_coupons - ?, commitment_coupons = commitment_coupons - ?, task_coupons = task_coupons - ? WHERE id = ?");
        $updSender->bind_param("iiiii", $sender_new_total, $att_sub, $com_sub, $tsk_sub, $senderId);
        if (!$updSender->execute()) {
            throw new Exception('فشل خصم الكوبونات من المرسل');
        }

        // 4. Add to recipient
        $rec_new_total = intval($recipient['coupons']) + $amount;
        $updRec = $conn->prepare("UPDATE students SET coupons = ?, attendance_coupons = attendance_coupons + ?, commitment_coupons = commitment_coupons + ?, task_coupons = task_coupons + ? WHERE id = ?");
        $updRec->bind_param("iiiii", $rec_new_total, $att_sub, $com_sub, $tsk_sub, $recipientId);
        if (!$updRec->execute()) {
            throw new Exception('فشل إضافة الكوبونات للمتلقي');
        }

        // 5. Add Audit Logs
        require_once 'audit.php';
        auditCouponChange($senderId, $sender['name'], $sender_total, $sender_new_total, "إرسال إلى {$recipient['name']} ($category)");
        auditCouponChange($recipientId, $recipient['name'], intval($recipient['coupons']), $rec_new_total, "استلام من {$sender['name']}");

        $conn->commit();
        sendJSON(['success' => true, 'message' => "تم إرسال الكوبونات بنجاح إلى {$recipient['name']}", 'newTotal' => $sender_new_total]);
    } catch (Exception $e) {
        if (isset($conn)) $conn->rollback();
        sendJSON(['success' => false, 'message' => $e->getMessage()]);
    }
}
