<?php
$db = new SQLite3(__DIR__ . '/database/database.sqlite');
$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
while ($row = $tables->fetchArray(SQLITE3_NUM)) {
    echo $row[0] . "\n";
}
