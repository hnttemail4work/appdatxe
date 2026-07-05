<?php



namespace App\Support;



use App\Models\PlatformSetting;



class AppBrandingSettings

{

    public const SETTING_KEY = 'app_branding';



    public const DEFAULT_APP_NAME = 'gozviet';



    public const DEFAULT_BRAND_TITLE = 'gozviet';



    public const DEFAULT_BRAND_TAGLINE = 'đặt xe liên tỉnh';



    public static function appName(): string

    {

        $stored = self::stored();

        $name = trim((string) ($stored['app_name'] ?? ''));



        return $name !== '' ? $name : (string) config('app.name', self::DEFAULT_APP_NAME);

    }



    public static function brandTitle(): string

    {

        $stored = self::stored();

        $title = trim((string) ($stored['brand_title'] ?? ''));



        return $title !== '' ? $title : self::DEFAULT_BRAND_TITLE;

    }



    public static function brandTagline(): string

    {

        $stored = self::stored();

        $tagline = trim((string) ($stored['brand_tagline'] ?? ''));



        return $tagline !== '' ? $tagline : self::DEFAULT_BRAND_TAGLINE;

    }



    /** @return array{app_name: string, brand_title: string, brand_tagline: string} */

    public static function forAdmin(): array

    {

        return [

            'app_name'       => self::appName(),

            'brand_title'    => self::brandTitle(),

            'brand_tagline'  => self::brandTagline(),

        ];

    }



    public static function saveBranding(string $appName, string $brandTitle, string $brandTagline): void

    {

        $stored = self::stored();

        $stored['app_name'] = trim($appName) !== '' ? trim($appName) : self::DEFAULT_APP_NAME;

        $stored['brand_title'] = trim($brandTitle) !== '' ? trim($brandTitle) : self::DEFAULT_BRAND_TITLE;

        $stored['brand_tagline'] = trim($brandTagline) !== '' ? trim($brandTagline) : self::DEFAULT_BRAND_TAGLINE;



        PlatformSetting::setValue(self::SETTING_KEY, $stored, 'ui');

    }



    /** @return array<string, mixed> */

    private static function stored(): array

    {

        $value = PlatformSetting::getValue(self::SETTING_KEY, []);



        return is_array($value) ? $value : [];

    }

}


