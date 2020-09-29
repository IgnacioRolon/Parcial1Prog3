<?php

require __DIR__."/vendor/autoload.php";
use \Firebase\JWT\JWT;

class Token{
    private static $key = "primerparcial";

    static function encode($payload) //Payload must be an array
    {
        $jwt = JWT::encode($payload, Token::$key);
        return $jwt;
    }

    static function verify() //If no error was return during decoding, returns true. Else, returns error.
    {
        try {
            $token = $_SERVER['HTTP_TOKEN'];
            $decoded = JWT::decode($token, Token::$key, array('HS256'));
            return true;
        } catch (\Throwable $th) {
            return false;
        }
    }

    static function decode() //Returns the decoded payload, or null if it was not possible to read token.
    {
        try {
            $token = $_SERVER['HTTP_TOKEN'];
            $decoded = JWT::decode($token, Token::$key, array('HS256'));
            return $decoded;
        } catch (\Throwable $th) {
            echo "Invalid token.";
            return null;
        }
    }
}









?>