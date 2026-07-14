<?php

namespace App\Payments;

use RuntimeException;

/** Sağlayıcı işlemi reddetti veya provizyon beklenen durumda değil. */
class PaymentException extends RuntimeException
{
}
