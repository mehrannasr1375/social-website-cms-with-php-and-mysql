<?php
class Table
{
    protected $data = array();

    public function __construct($data)//CAN`T return anything //sets the attributes of an object
    {//takes an array and creates an object with same array keys names keys
        foreach ($data as $key => $value) {
            if (array_key_exists($key,$this -> data))
                if (is_numeric($value))
                    $this -> data[$key] = (int)$value;//DB returns everything as string (turn into integer from a string)
                else
                    $this -> data[$key] = $value;
        }
    }

    public function __get($property)//call a NON-Public attribute from an object
    {
        if (array_key_exists($property,$this -> data))
            return $this -> data[$property];
        else
            die("invalid property (table)");
    }

    protected static function connect()
    {
        $dsn = "mysql:host=".HOST_NAME.";dbname=".DB_NAME.";charset=utf8";
        $conn = new PDO($dsn,DB_USER,DB_PASS);
        return $conn;//returns 'false' or an object from 'PDOStatement' class
    }

    protected static function disconnect($conn)
    {
        unset($conn);
    }
}