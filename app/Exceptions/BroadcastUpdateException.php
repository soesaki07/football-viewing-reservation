<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 放映を更新できなかったことを表す例外。メッセージは利用者にそのまま表示できる文言。
 */
class BroadcastUpdateException extends RuntimeException
{
    /**
     * @param  array<string, string>  $fieldErrors  入力欄ごとのエラー（空なら画面全体のエラー）
     */
    public function __construct(string $message, private readonly array $fieldErrors = [])
    {
        parent::__construct($message);
    }

    /**
     * @return array<string, string>
     */
    public function fieldErrors(): array
    {
        return $this->fieldErrors;
    }
}
