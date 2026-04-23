import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path/path.dart';
import 'package:sqflite/sqflite.dart';

final localDatabaseProvider = Provider<LocalDatabaseService>(
  (_) => LocalDatabaseService(),
);

class LocalDatabaseService {
  static Database? _db;

  Future<Database> get database async {
    _db ??= await _initDb();
    return _db!;
  }

  Future<Database> _initDb() async {
    final dbPath = await getDatabasesPath();
    final path = join(dbPath, 'ponto_offline.db');

    return openDatabase(
      path,
      version: 1,
      onCreate: (db, version) async {
        await db.execute('''
          CREATE TABLE offline_records (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            type TEXT NOT NULL,
            datetime TEXT NOT NULL,
            latitude REAL,
            longitude REAL,
            photo_url TEXT,
            device_id TEXT,
            is_mock_location INTEGER DEFAULT 0,
            synced INTEGER DEFAULT 0,
            created_at TEXT NOT NULL
          )
        ''');
      },
    );
  }

  Future<int> insertOfflineRecord(Map<String, dynamic> record) async {
    final db = await database;
    return db.insert('offline_records', {
      ...record,
      'created_at': DateTime.now().toIso8601String(),
    });
  }

  Future<List<Map<String, dynamic>>> getPendingRecords() async {
    final db = await database;
    return db.query(
      'offline_records',
      where: 'synced = 0',
      orderBy: 'datetime ASC',
    );
  }

  Future<void> markAsSynced(int id) async {
    final db = await database;
    await db.update(
      'offline_records',
      {'synced': 1},
      where: 'id = ?',
      whereArgs: [id],
    );
  }

  Future<void> markAllAsSynced(List<int> ids) async {
    if (ids.isEmpty) return;
    final db = await database;
    await db.update(
      'offline_records',
      {'synced': 1},
      where: 'id IN (${ids.map((_) => '?').join(',')})',
      whereArgs: ids,
    );
  }

  Future<int> getPendingCount() async {
    final db = await database;
    final result = await db.rawQuery(
      'SELECT COUNT(*) as count FROM offline_records WHERE synced = 0',
    );
    return result.first['count'] as int;
  }

  Future<void> clearSynced() async {
    final db = await database;
    await db.delete('offline_records', where: 'synced = 1');
  }
}

