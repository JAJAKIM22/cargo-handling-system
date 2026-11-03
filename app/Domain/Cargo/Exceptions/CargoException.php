<?php

namespace App\Domain\Cargo\Exceptions;

use DomainException;

/**
 * Base class for every business-rule violation in the cargo domain.
 * Catch this to handle "expected" rejections without swallowing real bugs.
 */
abstract class CargoException extends DomainException
{
}