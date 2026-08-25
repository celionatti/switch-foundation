<?php

declare(strict_types=1);

namespace Switch\Foundation\Data;

class MockGenerator
{
    private static array $firstNames = [
        'James', 'Mary', 'Robert', 'Patricia', 'John', 'Jennifer', 'Michael', 'Linda', 'David', 'Elizabeth',
        'William', 'Barbara', 'Richard', 'Susan', 'Joseph', 'Jessica', 'Thomas', 'Sarah', 'Charles', 'Karen',
        'Christopher', 'Nancy', 'Daniel', 'Lisa', 'Matthew', 'Betty', 'Anthony', 'Margaret', 'Mark', 'Sandra',
        'Donald', 'Ashley', 'Steven', 'Kimberly', 'Paul', 'Emily', 'Andrew', 'Donna', 'Joshua', 'Michelle',
        'Kenneth', 'Carol', 'Kevin', 'Amanda', 'Brian', 'Melissa', 'George', 'Deborah', 'Edward', 'Stephanie'
    ];

    private static array $lastNames = [
        'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez',
        'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin',
        'Lee', 'Perez', 'Thompson', 'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson',
        'Walker', 'Young', 'Allen', 'King', 'Wright', 'Scott', 'Torres', 'Nguyen', 'Hill', 'Flores',
        'Green', 'Adams', 'Nelson', 'Baker', 'Hall', 'Rivera', 'Campbell', 'Mitchell', 'Carter', 'Roberts'
    ];

    private static array $domains = [
        'example.com', 'mail.test', 'devstudio.io', 'switch.dev', 'acme.org', 'cloudapp.net', 'nexus.tech'
    ];

    private static array $cities = [
        'New York', 'San Francisco', 'London', 'Tokyo', 'Berlin', 'Paris', 'Sydney', 'Toronto', 'Singapore', 'Amsterdam',
        'Austin', 'Seattle', 'Dublin', 'Stockholm', 'Zurich', 'Dubai', 'Seoul', 'Barcelona', 'Melbourne', 'Boston'
    ];

    private static array $countries = [
        ['name' => 'United States', 'code' => 'US'],
        ['name' => 'United Kingdom', 'code' => 'GB'],
        ['name' => 'Germany', 'code' => 'DE'],
        ['name' => 'France', 'code' => 'FR'],
        ['name' => 'Japan', 'code' => 'JP'],
        ['name' => 'Canada', 'code' => 'CA'],
        ['name' => 'Australia', 'code' => 'AU'],
        ['name' => 'Netherlands', 'code' => 'NL'],
        ['name' => 'Singapore', 'code' => 'SG'],
        ['name' => 'Sweden', 'code' => 'SE'],
    ];

    private static array $companies = [
        'Acme Corporation', 'Nexus Dynamics', 'Vortex Systems', 'Horizon Interactive', 'Apex Technologies',
        'Pulse Media', 'Quantum Labs', 'Echo Digital', 'Starlight Studios', 'Omni Solutions', 'Synergy Global'
    ];

    private static array $jobTitles = [
        'Software Engineer', 'Product Manager', 'UX Designer', 'DevOps Lead', 'CTO', 'Frontend Architect',
        'Data Scientist', 'Security Analyst', 'VP of Engineering', 'Creative Director', 'Operations Specialist'
    ];

    private static array $loremWords = [
        'lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing', 'elit', 'sed', 'do',
        'eiusmod', 'tempor', 'incididunt', 'ut', 'labore', 'et', 'dolore', 'magna', 'aliqua', 'enim',
        'ad', 'minim', 'veniam', 'quis', 'nostrud', 'exercitation', 'ullamco', 'laboris', 'nisi', 'aliquip',
        'ex', 'ea', 'commodo', 'consequat', 'duis', 'aute', 'irure', 'in', 'reprehenderit', 'voluptate',
        'velit', 'esse', 'cillum', 'eu', 'fugiat', 'nulla', 'pariatur', 'excepteur', 'sint', 'occaecat'
    ];

    public function name(): string
    {
        return $this->firstName() . ' ' . $this->lastName();
    }

    public function firstName(): string
    {
        return self::$firstNames[array_rand(self::$firstNames)];
    }

    public function lastName(): string
    {
        return self::$lastNames[array_rand(self::$lastNames)];
    }

    public function username(?string $name = null): string
    {
        $base = $name ?? $this->name();
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $base));
        return $slug . mt_rand(10, 999);
    }

    public function email(?string $name = null): string
    {
        $user = $name ? strtolower(preg_replace('/[^a-zA-Z0-9]/', '.', $name)) : $this->username();
        $domain = self::$domains[array_rand(self::$domains)];
        return $user . '@' . $domain;
    }

    public function avatar(?string $seed = null, int $size = 150): string
    {
        $id = $seed ? urlencode($seed) : md5((string) mt_rand());
        return "https://api.dicebear.com/7.x/avataaars/svg?seed={$id}&size={$size}";
    }

    public function title(int $words = 4): string
    {
        return ucfirst(implode(' ', (array) $this->randomElements(self::$loremWords, $words)));
    }

    public function sentence(int $words = 8): string
    {
        $w = (array) $this->randomElements(self::$loremWords, $words);
        return ucfirst(implode(' ', $w)) . '.';
    }

    public function paragraph(int $sentences = 3): string
    {
        $out = [];
        for ($i = 0; $i < $sentences; $i++) {
            $out[] = $this->sentence(mt_rand(6, 12));
        }
        return implode(' ', $out);
    }

    public function text(int $words = 15): string
    {
        return $this->paragraph(max(1, (int) ceil($words / 8)));
    }

    public function price(float $min = 9.99, float $max = 499.99, int $decimals = 2): float
    {
        $val = $min + mt_rand() / mt_getrandmax() * ($max - $min);
        return round($val, $decimals);
    }

    public function integer(int $min = 1, int $max = 1000): int
    {
        return mt_rand($min, $max);
    }

    public function float(float $min = 0.0, float $max = 100.0, int $decimals = 2): float
    {
        $val = $min + mt_rand() / mt_getrandmax() * ($max - $min);
        return round($val, $decimals);
    }

    public function boolean(int $chanceOfTrue = 50): bool
    {
        return mt_rand(1, 100) <= $chanceOfTrue;
    }

    public function date(string $format = 'Y-m-d', string $from = '-1 year', string $to = 'now'): string
    {
        $start = strtotime($from) ?: time() - 31536000;
        $end = strtotime($to) ?: time();
        $randomTimestamp = mt_rand(min($start, $end), max($start, $end));
        return date($format, $randomTimestamp);
    }

    public function dateTime(string $format = 'Y-m-d H:i:s'): string
    {
        return $this->date($format);
    }

    public function phone(): string
    {
        return sprintf('+1 (%03d) %03d-%04d', mt_rand(200, 999), mt_rand(200, 999), mt_rand(1000, 9999));
    }

    public function city(): string
    {
        return self::$cities[array_rand(self::$cities)];
    }

    public function country(): string
    {
        $c = self::$countries[array_rand(self::$countries)];
        return $c['name'];
    }

    public function countryCode(): string
    {
        $c = self::$countries[array_rand(self::$countries)];
        return $c['code'];
    }

    public function address(): array
    {
        $c = self::$countries[array_rand(self::$countries)];
        return [
            'street' => mt_rand(100, 9999) . ' ' . $this->lastName() . ' St',
            'city' => $this->city(),
            'country' => $c['name'],
            'country_code' => $c['code'],
            'zip' => sprintf('%05d', mt_rand(10000, 99999)),
        ];
    }

    public function company(): string
    {
        return self::$companies[array_rand(self::$companies)];
    }

    public function jobTitle(): string
    {
        return self::$jobTitles[array_rand(self::$jobTitles)];
    }

    public function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public function color(): string
    {
        return sprintf('#%06X', mt_rand(0, 0xFFFFFF));
    }

    public function image(int $width = 640, int $height = 480, string $category = 'tech'): string
    {
        return "https://picsum.photos/{$width}/{$height}?random=" . mt_rand(1, 10000);
    }

    public function randomElement(array $elements): mixed
    {
        if (empty($elements)) {
            return null;
        }
        return $elements[array_rand($elements)];
    }

    public function randomElements(array $elements, int $count = 1): array
    {
        if (empty($elements)) {
            return [];
        }
        $count = min($count, count($elements));
        $keys = (array) array_rand($elements, $count);
        $result = [];
        foreach ($keys as $k) {
            $result[] = $elements[$k];
        }
        return $result;
    }
}
