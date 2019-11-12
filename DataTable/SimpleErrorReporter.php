<?php


namespace DataTable;


class SimpleErrorReporter implements iErrorReporter
{

    /**
     *
     * @var string
     */
    private $errorMessage;

    /**
     *
     * @var int
     */
    private $errorCode;

    /**
     * @var array
     */
    private $warnings;



    public function __construct()
    {
        $this->resetError();
        $this->warnings = [];
    }

    /**
     * Returns a string describing the last error
     *
     * @return string
     */
    public function getErrorMessage() : string
    {
        return $this->errorMessage;
    }

    public function getErrorCode() : int
    {
        return $this->errorCode;
    }

    public function getWarnings() : array
    {
        return $this->warnings;
    }

    public function setErrorMessage(string $message) : void
    {
        $this->errorMessage = $message;
    }

    public function setErrorCode(int $code) : void
    {
        $this->errorCode = $code;
    }

    public function addWarning(string $warningMessage) : void
    {
        $this->warnings[] = $warningMessage;
    }

    public function setError(string $msg, int $code): void
    {
        $this->setErrorMessage($msg);
        $this->setErrorCode($code);
    }

    public function resetError(): void
    {
        $this->setErrorCode(0);
        $this->setErrorMessage('');
    }
}