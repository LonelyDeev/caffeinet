<?php

namespace App\Services\Finance;

use RuntimeException;

/** خطای عملیات کیف پول (موجودی ناکافی و…) */
class WalletException extends RuntimeException
{
}
