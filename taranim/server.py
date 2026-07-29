#!/usr/bin/env python3
import http.server
import socketserver
import json
import sqlite3
import urllib.parse
import urllib.request
import os
import re
import threading
import time

PORT = 8080
DB_PATH = os.path.join(os.path.dirname(__file__), 'database.sqlite')
PLAYLISTS_FILE = os.path.join(os.path.dirname(__file__), 'playlists.json')

if not os.path.exists(PLAYLISTS_FILE):
    with open(PLAYLISTS_FILE, 'w', encoding='utf-8') as f:
        json.dump([], f)

# Global Live Presentation State for OBS HTTP Sync
LIVE_STATE = {
    'type': 'PRESENT_LINE',
    'text': '',
    'songTitle': '',
    'font': "'Alexandria', sans-serif",
    'fontSize': 54,
    'chroma': 'black',
    'isBlank': False,
    'textColor': '#ffffff',
    'strokeWidth': 0,
    'strokeColor': '#000000',
    'shadowBlur': 0,
    'pos': {'left': 960, 'top': 540}
}

def get_db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn

# AUTOMATIC ONLINE DATABASE SYNC BACKGROUND WORKER
def sync_online_database():
    """Background worker to keep local SQLite database synced with online catalog."""
    time.sleep(5) # Wait for server startup
    print("🔄 Checking for new online songs database updates...")
    try:
        req = urllib.request.Request(
            'https://raw.githubusercontent.com/tashbe7na/database/main/latest.json',
            headers={'User-Agent': 'Mozilla/5.0'}
        )
        with urllib.request.urlopen(req, timeout=10) as response:
            if response.status == 200:
                data = json.loads(response.read().decode('utf-8'))
                if isinstance(data, list):
                    conn = get_db()
                    cursor = conn.cursor()
                    inserted = 0
                    for song in data:
                        cursor.execute('SELECT id FROM songs WHERE title = ?;', (song.get('title'),))
                        if not cursor.fetchone():
                            cursor.execute(
                                'INSERT INTO songs (title, notes) VALUES (?, ?);',
                                (song.get('title'), song.get('notes', ''))
                            )
                            inserted += 1
                    conn.commit()
                    conn.close()
                    if inserted > 0:
                        print(f"✅ Auto-synced {inserted} new songs into local database!")
    except Exception as e:
        print("ℹ️ Local database active (offline mode ready).")

class SundaySchoolTaranimHandler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=os.path.join(os.path.dirname(__file__), 'public'), **kwargs)

    def do_GET(self):
        parsed = urllib.parse.urlparse(self.path)
        path = parsed.path
        query = urllib.parse.parse_qs(parsed.query)

        if path.startswith('/api/'):
            self.handle_api_get(path, query)
        else:
            super().do_GET()

    def do_POST(self):
        parsed = urllib.parse.urlparse(self.path)
        path = parsed.path
        content_length = int(self.headers.get('Content-Length', 0))
        body = self.rfile.read(content_length).decode('utf-8') if content_length > 0 else '{}'
        try:
            data = json.loads(body)
        except Exception:
            data = {}

        if path == '/api/live':
            LIVE_STATE.update(data)
            self.send_json({'success': True})

        elif path == '/api/playlists':
            with open(PLAYLISTS_FILE, 'w', encoding='utf-8') as f:
                json.dump(data, f, ensure_ascii=False, indent=2)
            self.send_json({'success': True})

        else:
            self.send_error(404, "Endpoint not found")

    def handle_api_get(self, path, query):
        conn = get_db()
        cursor = conn.cursor()

        if path == '/api/live':
            conn.close()
            self.send_json(LIVE_STATE)
            return

        elif path == '/api/songs':
            q = query.get('q', [''])[0].strip()
            limit = int(query.get('limit', ['50'])[0])
            offset = int(query.get('offset', ['0'])[0])

            results = []

            # Check if Bible Reference query (e.g. "رو 1", "1 صم 3", "مز 23", "يو 3")
            if q:
                match = re.match(r'^([0-9]?\s*[\u0600-\u06FF]+)\s+([0-9]+)', q)
                if match:
                    b_name = match.group(1).strip()
                    c_num = int(match.group(2))

                    cursor.execute('''
                        SELECT id, book, title, abbr FROM books 
                        WHERE abbr = ? OR title = ? OR title LIKE ? OR abbr LIKE ?
                        ORDER BY 
                            (CASE WHEN abbr = ? THEN 1 ELSE 0 END) DESC,
                            (CASE WHEN title = ? THEN 1 ELSE 0 END) DESC,
                            (CASE WHEN abbr LIKE ? THEN 1 ELSE 0 END) DESC
                        LIMIT 1;
                    ''', (b_name, b_name, f'{b_name}%', f'{b_name}%', b_name, b_name, f'{b_name}%'))
                    
                    b_row = cursor.fetchone()
                    if b_row:
                        cursor.execute('''
                            SELECT c.id, c.item_id FROM chapters c 
                            JOIN bible_chapters bc ON bc.id = c.bible_chapter 
                            WHERE bc.book = ? AND bc.number = ?;
                        ''', (b_row['book'], c_num))
                        c_row = cursor.fetchone()
                        if c_row:
                            cursor.execute('''
                                SELECT sg.content FROM verses v 
                                JOIN slides sl ON sl.verse = v.id
                                JOIN segments sg ON sg.slide = sl.id
                                WHERE v.item_id = ? AND sg.content IS NOT NULL AND sg.content != ''
                                LIMIT 6;
                            ''', (c_row['item_id'],))
                            v_preview = [r[0] for r in cursor.fetchall()]
                            notes_text = "\n".join(v_preview) if v_preview else f'شاهد كتابي مقدس ({b_row["abbr"]} {c_num})'

                            results.append({
                                'id': f'bible_{c_row["id"]}',
                                'item_id': c_row['item_id'],
                                'title': f'📖 {b_row["title"]} - الأصحاح {c_num}',
                                'notes': notes_text,
                                'is_bible': True
                            })

            # Search songs (STRICT DEDUPLICATION BY SONG ID USING GROUP BY)
            if q:
                cursor.execute('''
                    SELECT s.id, s.item_id, s.title, s.media_url, 
                           GROUP_CONCAT(DISTINCT sg.content) as notes
                    FROM songs s
                    LEFT JOIN verses v ON v.item_id = s.item_id
                    LEFT JOIN slides sl ON sl.verse = v.id
                    LEFT JOIN segments sg ON sg.slide = sl.id
                    WHERE s.title LIKE ? OR sg.content LIKE ?
                    GROUP BY s.id
                    LIMIT ? OFFSET ?;
                ''', (f'%{q}%', f'%{q}%', limit, offset))
            else:
                cursor.execute('''
                    SELECT id, item_id, title, media_url, notes
                    FROM songs
                    ORDER BY id ASC
                    LIMIT ? OFFSET ?;
                ''', (limit, offset))

            songs = [dict(row) for row in cursor.fetchall()]
            results.extend(songs)

            conn.close()
            self.send_json({'songs': results, 'total': len(results), 'limit': limit, 'offset': offset})

        elif path.startswith('/api/song/'):
            raw_id = path.split('/')[-1]

            if raw_id.startswith('bible_'):
                chap_id = int(raw_id.replace('bible_', ''))
                cursor.execute('SELECT c.id, c.item_id, c.bible_chapter FROM chapters c WHERE c.id = ?;', (chap_id,))
                c_row = cursor.fetchone()
                if not c_row:
                    self.send_json({'error': 'Bible chapter not found'}, status=404)
                    conn.close()
                    return

                item_id = c_row['item_id']

                cursor.execute('''
                    SELECT b.title, b.abbr, bc.number FROM bible_chapters bc
                    JOIN books b ON b.book = bc.book
                    WHERE bc.id = ?;
                ''', (c_row['bible_chapter'],))
                meta = cursor.fetchone()
                title = f'📖 {meta["title"]} - الأصحاح {meta["number"]}' if meta else 'الشاهد الكتابي'

                cursor.execute('SELECT id, type FROM verses WHERE item_id = ? ORDER BY id;', (item_id,))
                verses = [dict(r) for r in cursor.fetchall()]

                for v in verses:
                    cursor.execute('SELECT id, heading FROM slides WHERE verse = ? ORDER BY id;', (v['id'],))
                    slides = [dict(r) for r in cursor.fetchall()]
                    for sl in slides:
                        cursor.execute('SELECT content FROM segments WHERE slide = ? ORDER BY id;', (sl['id'],))
                        sl['lines'] = [r[0] for r in cursor.fetchall() if r[0]]
                        sl['text'] = '\n'.join(sl['lines'])
                    v['slides'] = slides

                bible_data = {
                    'id': raw_id,
                    'item_id': item_id,
                    'title': title,
                    'verses': verses,
                    'chords': []
                }
                conn.close()
                self.send_json(bible_data)
                return

            else:
                try:
                    song_id = int(raw_id)
                except ValueError:
                    self.send_json({'error': 'Invalid ID'}, status=400)
                    conn.close()
                    return

                cursor.execute('SELECT id, item_id, title, media_url, notes FROM songs WHERE id = ?;', (song_id,))
                song_row = cursor.fetchone()
                if not song_row:
                    self.send_json({'error': 'Song not found'}, status=404)
                    conn.close()
                    return

                song = dict(song_row)

                cursor.execute('''
                    SELECT a.ar_name FROM song_authors sa 
                    JOIN authors a ON a.id = sa.author 
                    WHERE sa.song = ?;
                ''', (song_id,))
                song['authors'] = [r[0] for r in cursor.fetchall() if r[0]]

                cursor.execute('SELECT id, type FROM verses WHERE item_id = ? ORDER BY id;', (song['item_id'],))
                verses = [dict(r) for r in cursor.fetchall()]

                for v in verses:
                    cursor.execute('SELECT id, heading FROM slides WHERE verse = ? ORDER BY id;', (v['id'],))
                    slides = [dict(r) for r in cursor.fetchall()]
                    for sl in slides:
                        cursor.execute('SELECT content FROM segments WHERE slide = ? ORDER BY id;', (sl['id'],))
                        sl['lines'] = [r[0] for r in cursor.fetchall() if r[0]]
                        sl['text'] = '\n'.join(sl['lines'])
                    v['slides'] = slides

                song['verses'] = verses

                segment_ids = [sl['id'] for v in verses for sl in v['slides']]
                if segment_ids:
                    placeholders = ','.join(['?'] * len(segment_ids))
                    cursor.execute(f'SELECT * FROM chords_position WHERE segment IN ({placeholders});', segment_ids)
                    song['chords'] = [dict(r) for r in cursor.fetchall()]
                else:
                    song['chords'] = []

                conn.close()
                self.send_json(song)

        elif path == '/api/playlists':
            conn.close()
            with open(PLAYLISTS_FILE, 'r', encoding='utf-8') as f:
                playlists = json.load(f)
            self.send_json(playlists)

        else:
            conn.close()
            self.send_json({'error': 'Not found'}, status=404)

    def send_json(self, data, status=200):
        body = json.dumps(data, ensure_ascii=False).encode('utf-8')
        self.send_response(status)
        self.send_header('Content-Type', 'application/json; charset=utf-8')
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        self.wfile.write(body)

if __name__ == '__main__':
    os.makedirs(os.path.join(os.path.dirname(__file__), 'public'), exist_ok=True)

    # Start background online database sync worker thread
    sync_thread = threading.Thread(target=sync_online_database, daemon=True)
    sync_thread.start()

    socketserver.TCPServer.allow_reuse_address = True
    with socketserver.TCPServer(("", PORT), SundaySchoolTaranimHandler) as httpd:
        print(f"🚀 Sunday School Taranim Server running at http://localhost:{PORT}")
        httpd.serve_forever()
