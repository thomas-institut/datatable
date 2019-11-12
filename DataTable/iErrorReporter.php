<?php


namespace DataTable;


interface iErrorReporter
{

    public function resetError() : void;
    public function getErrorMessage() : string;
    public function setErrorMessage(string $message) : void;

    public function setError(string $msg, int $code) : void;

    public function getErrorCode() : int;
    public function setErrorCode(int $code) : void;

    public function getWarnings() : array;
    public function addWarning(string $warningMessage) : void;

}