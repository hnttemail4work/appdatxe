<?php

namespace App\Support;

/** TTL / copy OTP dùng chung (đăng ký, chờ duyệt, quên PIN, gửi lại…). */
final class AuthOtp
{
    public const TTL_MINUTES = 30;

    public static function ttlLabel(): string
    {
        return self::TTL_MINUTES.' phút';
    }

    public static function adminProvideHint(): string
    {
        return 'Nhập mã OTP 6 số (admin cung cấp, hiệu lực '.self::ttlLabel().').';
    }

    public static function resendSuccess(): string
    {
        return 'Đã gửi lại mã OTP. Liên hệ admin để nhận mã (hiệu lực '.self::ttlLabel().').';
    }

    public static function registerSuccess(): string
    {
        return 'Đã gửi hồ sơ. Admin sẽ duyệt trong khoảng '.self::ttlLabel().' — giữ trang này, sau khi duyệt nhập mã OTP (admin cung cấp).';
    }

    public static function awaitingApprovalOtpNotice(bool $isCustomer = true): string
    {
        if ($isCustomer) {
            return 'Hồ sơ đang chờ duyệt CCCD (khoảng '.self::ttlLabel().'). Nhập mã OTP admin cung cấp qua tin nhắn số điện thoại hoặc zalo.';
        }

        return 'Hồ sơ tài xế đang chờ duyệt (khoảng '.self::ttlLabel().'). Nhập mã OTP admin cung cấp qua tin nhắn số điện thoại hoặc zalo.';
    }

    public static function approvedOtpReady(): string
    {
        return 'Đã duyệt. Mã OTP đã có ở tab OTP / Reset — gửi cho người dùng để đăng nhập lần đầu (hiệu lực '.self::ttlLabel().').';
    }

    public static function pendingApprovalNotice(bool $isCustomer = true): string
    {
        if ($isCustomer) {
            return 'Hồ sơ đang chờ duyệt CCCD (khoảng '.self::ttlLabel().'). Bạn có thể xem trang chủ — đặt xe sau khi được duyệt.';
        }

        return 'Hồ sơ tài xế đang chờ duyệt (khoảng '.self::ttlLabel().'). Nhận chuyến sau khi được duyệt.';
    }

    public static function pendingExpiredLoginMessage(): string
    {
        return 'Hết '.self::ttlLabel().' chờ duyệt mà chưa có phản hồi. Vui lòng đăng nhập / đăng ký lại.';
    }

    /** Lý do từ chối tự động khi hết TTL chờ duyệt (hiện ở admin). */
    public static function pendingExpiredRejectionReason(): string
    {
        return 'Hết '.self::ttlLabel().' chờ duyệt — hệ thống tự từ chối vì chưa có phản hồi từ admin.';
    }
}

