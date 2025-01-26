<?php

namespace ADT\DoctrineLoggable;

interface Exception
{

}

class UnexpectedValueException extends \UnexpectedValueException implements Exception
{

}
