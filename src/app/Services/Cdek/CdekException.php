<?php

namespace App\Services\Cdek;

use RuntimeException;

/**
 * CDEK could not be reached, refused the credentials or could not price a parcel.
 */
class CdekException extends RuntimeException {}
