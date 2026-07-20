<?php

namespace App\Exceptions;

use Exception;

/**
 * Vi phạm quy tắc nghiệp vụ đã xác nhận (khác lỗi hệ thống/validation input) —
 * ví dụ quy tắc 250L trong ApproveProductionOrderService. Controller bắt riêng
 * exception này để trả 422 kèm message rõ ràng cho người vận hành.
 */
class BusinessRuleException extends Exception
{
}
