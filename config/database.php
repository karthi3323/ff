<?php
class Database {
    private $host = "localhost";
    private $port = "5432";
    private $db_name = "ff_dbs";
    private $username = "ff_adm";
    private $password = "friends";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $dsn = "pgsql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";options='--client_encoding=UTF8'";
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $exception) {
            // More detailed error information
            $error_message = "Database Connection Failed:\n";
            $error_message .= "Error: " . $exception->getMessage() . "\n";
            $error_message .= "Host: " . $this->host . "\n";
            $error_message .= "Database: " . $this->db_name . "\n";
            $error_message .= "Username: " . $this->username . "\n";
            
            error_log($error_message);
            throw new Exception("Unable to connect to database. Please check your configuration.");
        }
        return $this->conn;
    }
}
?>