<?php
declare(strict_types=1);

// Simple migration runner for SQLite

require __DIR__ . '/../src/Database.php';

function runMigrations(PDO $pdo): void
{
    // Enable foreign keys
    $pdo->exec('PRAGMA foreign_keys = ON');

    // Users
    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS users (
    id TEXT PRIMARY KEY,
    fullname TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    role TEXT NOT NULL CHECK(role IN ('user','firma_admin','admin')),
    company_id TEXT NULL,
    balance REAL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
);
SQL);

    // Companies
    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS companies (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
SQL);

    // Trips
    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS trips (
    id TEXT PRIMARY KEY,
    company_id TEXT NOT NULL,
    departure_location TEXT NOT NULL,
    arrival_location TEXT NOT NULL,
    departure_time DATETIME NOT NULL,
    arrival_time DATETIME NOT NULL,
    price REAL NOT NULL,
    seat_count INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);
SQL);

    // Tickets
    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS tickets (
    id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL,
    trip_id TEXT NOT NULL,
    seat_number INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'ACTIVE' CHECK(status IN ('ACTIVE','CANCELLED')),
    purchase_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (trip_id, seat_number),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
);
SQL);

    // Coupons
    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS coupons (
    id TEXT PRIMARY KEY,
    code TEXT NOT NULL UNIQUE,
    discount_rate REAL NOT NULL,
    usage_limit INTEGER NOT NULL,
    expiry_date DATETIME NOT NULL,
    company_id TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);
SQL);
}

$pdo = Database::getConnection();
runMigrations($pdo);
echo "Migrations completed successfully.\n";


