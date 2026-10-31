<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 予約を確定できなかったことを表す例外。メッセージは利用者にそのまま表示できる文言。
 */
class ReservationException extends RuntimeException {}
