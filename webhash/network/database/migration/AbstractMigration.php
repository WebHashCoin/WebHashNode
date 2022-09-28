<?php

namespace WebHash\Network\Database\Migration;

use WebHash\Network\Database\DatabaseConnection;

abstract class AbstractMigration {

        /**
        * @var DatabaseConnection
        */
        protected DatabaseConnection $db;

        public function __construct(DatabaseConnection $db) {
            $this->db = $db;
        }

        public function up() {
            $this->db->beginTransaction();
            $this->upgrade();
            $this->db->commit();
        }

        public function down() {
            $this->db->beginTransaction();
            $this->downgrade();
            $this->db->commit();
        }

        abstract protected function upgrade();

        abstract protected function downgrade();

        abstract public function version() : int;
}
