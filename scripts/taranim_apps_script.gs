/**
 * Sunday School - Taranim Email Notification Service
 * Google Apps Script (Code.gs)
 * 
 * Instructions to fix "Apps Script doesn't send email":
 * 1. Open https://script.google.com and open your Taranim script project.
 * 2. Replace the Code.gs contents with this entire file and save (Ctrl+S / Cmd+S).
 * 3. Click "Deploy" (نشر) -> "Manage deployments" (إدارة عمليات النشر).
 * 4. Click the Edit pencil icon on your deployment:
 *    - Version: "New version" (إصدار جديد)
 *    - Execute as: "Me" (حسابي - peterfayez107@gmail.com)
 *    - Who has access: "Anyone" (أي شخص) <--- CRITICAL: Must be Anyone so server & website can send without Google login!
 * 5. Click "Deploy" (نشر).
 * 6. If prompted for permissions, click "Review Permissions" -> Choose account -> "Advanced" -> "Go to ... (unsafe)" -> "Allow".
 */

const DEFAULT_ADMIN_EMAIL = 'peterfayez107@gmail.com';

function doGet(e) {
  var params = (e && e.parameter) ? e.parameter : {};
  var action = (params.action || '').toString();

  if (action === 'test' || !action) {
    return ContentService.createTextOutput(JSON.stringify({
      status: 'active',
      service: 'Sunday School Songs Email Notification Service',
      quotaRemaining: MailApp.getRemainingDailyQuota(),
      timestamp: new Date().toISOString()
    })).setMimeType(ContentService.MimeType.JSON);
  }

  return handleEmailRequest(params);
}

function doPost(e) {
  try {
    var data = {};
    if (e && e.postData && e.postData.contents) {
      try {
        data = JSON.parse(e.postData.contents);
      } catch (err) {
        data = (e && e.parameter) ? e.parameter : {};
      }
    } else if (e && e.parameter) {
      data = e.parameter;
    }

    return handleEmailRequest(data);
  } catch (error) {
    return ContentService.createTextOutput(JSON.stringify({
      status: 'error',
      message: 'doPost error: ' + error.toString()
    })).setMimeType(ContentService.MimeType.JSON);
  }
}

function handleEmailRequest(data) {
  try {
    var to = data.to || DEFAULT_ADMIN_EMAIL;
    var subject = data.subject || '🎵 إشعار جديد بخصوص ترنيمة';
    var htmlBody = data.htmlBody || data.body || '';

    if (!htmlBody) {
      return ContentService.createTextOutput(JSON.stringify({
        status: 'error',
        message: 'Missing htmlBody'
      })).setMimeType(ContentService.MimeType.JSON);
    }

    var quota = MailApp.getRemainingDailyQuota();
    if (quota < 1) {
      return ContentService.createTextOutput(JSON.stringify({
        status: 'error',
        message: 'Daily email quota exhausted'
      })).setMimeType(ContentService.MimeType.JSON);
    }

    MailApp.sendEmail({
      to: to,
      subject: subject,
      htmlBody: htmlBody
    });

    return ContentService.createTextOutput(JSON.stringify({
      status: 'success',
      message: 'Email sent successfully to ' + to,
      remainingQuota: MailApp.getRemainingDailyQuota()
    })).setMimeType(ContentService.MimeType.JSON);

  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({
      status: 'error',
      message: 'MailApp error: ' + err.toString()
    })).setMimeType(ContentService.MimeType.JSON);
  }
}
