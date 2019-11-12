<?php


namespace DataTable;


abstract class ErrorLogger implements iErrorReporter
{
    abstract public function resetError() : void;
    abstract public function setErrorMessage(string $message) : void;
    abstract public function setErrorCode(int $code) : void;
    abstract public function setError(string $msg, int $code) : void;
    abstract public function addWarning(string $warningMessage) : void;
}