import sqlite3
import sys
import os

def merge_databases(source_db_path, target_db_path="taranim/database.sqlite"):
    """
    Merges any new songs/verses from a downloaded Tasbe7na OPFS SQLite database
    into the primary target database.sqlite file automatically.
    """
    if not os.path.exists(source_db_path):
        print(f"❌ Error: Source database file '{source_db_path}' not found!")
        print("Usage: python3 merge_tasbe7na_db.py <path_to_downloaded_sqlite_file>")
        return

    if not os.path.exists(target_db_path):
        print(f"❌ Error: Target database file '{target_db_path}' not found!")
        return

    print(f"🔄 Comparing and merging new data from '{source_db_path}' into '{target_db_path}'...")

    src_conn = sqlite3.connect(source_db_path)
    src_cursor = src_conn.cursor()

    tgt_conn = sqlite3.connect(target_db_path)
    tgt_cursor = tgt_conn.cursor()

    # 1. Fetch existing records from target database
    tgt_cursor.execute("SELECT item_id, title FROM songs")
    existing_records = tgt_cursor.fetchall()
    existing_item_ids = {r[0] for r in existing_records if r[0] is not None}
    existing_titles = {r[1].strip() for r in existing_records if r[1]}

    # 2. Inspect source database tables
    src_cursor.execute("SELECT name FROM sqlite_master WHERE type='table';")
    tables = [t[0] for t in src_cursor.fetchall() if t[0] != 'sqlite_sequence']

    song_table = None
    for candidate in ['songs', 'song', 'taranim', 'items', 'lyrics']:
        if candidate in tables:
            song_table = candidate
            break

    if not song_table and tables:
        song_table = tables[0]

    if not song_table:
        print("❌ Error: Could not find table inside source SQLite file.")
        src_conn.close()
        tgt_conn.close()
        return

    src_cursor.execute(f"PRAGMA table_info({song_table})")
    columns = [c[1].lower() for c in src_cursor.fetchall()]

    title_col = next((c for c in ['title', 'name', 'song_title'] if c in columns), None)
    text_col = next((c for c in ['notes', 'text', 'content', 'lyrics', 'verses'] if c in columns), None)
    item_id_col = next((c for c in ['item_id', 'id', 'song_id'] if c in columns), None)

    if not title_col:
        print("❌ Error: Could not locate title column in source table!")
        src_conn.close()
        tgt_conn.close()
        return

    query = f"SELECT {item_id_col or 'NULL'}, {title_col}, {text_col or 'NULL'} FROM {song_table}"
    src_cursor.execute(query)
    rows = src_cursor.fetchall()

    added_count = 0

    for item_id, title, notes in rows:
        if not title or not str(title).strip():
            continue

        clean_title = str(title).strip()
        clean_notes = notes if isinstance(notes, str) else (str(notes) if notes is not None else '')

        if item_id in existing_item_ids or clean_title in existing_titles:
            continue

        tgt_cursor.execute(
            "INSERT INTO songs (item_id, title, language, notes) VALUES (?, ?, 1, ?)",
            (item_id, clean_title, clean_notes)
        )
        existing_titles.add(clean_title)
        if item_id:
            existing_item_ids.add(item_id)
        added_count += 1

    tgt_conn.commit()

    tgt_cursor.execute("SELECT COUNT(*) FROM songs")
    new_total = tgt_cursor.fetchone()[0]

    print(f"✅ Merge complete! Added {added_count} new songs into {target_db_path}.")
    print(f"📊 New total song count in database.sqlite: {new_total} songs.")

    src_conn.close()
    tgt_conn.close()

if __name__ == "__main__":
    source_file = sys.argv[1] if len(sys.argv) > 1 else "downloaded_tasbe7na.sqlite"
    merge_databases(source_file)
