<?php

namespace WebHash\Network\Database;

class DatabaseConnection extends \PDO
{
    private string $type = "sqlite";

    private string $host = "localhost";
    private string $port = "3306";
    private string $user = "root";
    private string $password = "";
    private string $database = "webhash";

    private string $sqlitePath = "../../../webhash.db";

    public function __construct()
    {
        if($this->type == "sqlite") {
            //create sqlite file
            $this->createSqliteFile();
            parent::__construct("sqlite:" . __DIR__ . "/" . $this->sqlitePath);
        } else {
            parent::__construct("mysql:host=localhost;dbname=webhash", "root", "");
        }
        $this->startMigration();
    }

    private function createSqliteFile()
    {
        if(!file_exists(__DIR__ . "/" . $this->sqlitePath)) {
            $file = fopen(__DIR__ . "/" . $this->sqlitePath, "w");
            fclose($file);
        }
    }

    private function startMigration()
    {
        $migrations = $this->getMigrations();
        $this->migrate($migrations);
    }

    private function getMigrations(): array
    {
        $migrations = glob(__DIR__ . "/migration/Migration*.php");
        $namespace = "WebHash\\Network\\Database\\Migration\\";
        return array_map(function ($migration) use ($namespace) {
            return str_replace('.php', '', str_replace(__DIR__ . '/migration/', $namespace, $migration));
        }, $migrations);
    }

    private function migrate(array $migrations)
    {
        $this->exec("CREATE TABLE IF NOT EXISTS `migration` (
            `id` INTEGER PRIMARY KEY AUTOINCREMENT,
            `version` INTEGER NOT NULL
        )");

        $currentVersion = $this->getCurrentVersion();
        foreach ($migrations as $migration) {
            $migration = new $migration($this);
            if($migration->version() > $currentVersion) {
                $migration->up();
                $this->exec("INSERT INTO `migration` (`version`) VALUES (" . $migration->version() . ")");
            }
        }
    }

    private function getCurrentVersion()
    {
        $result = $this->query("SELECT `version` FROM `migration` ORDER BY `version` DESC LIMIT 1");
        $result = $result->fetch();
        return $result["version"] ?? 0;
    }
}
