<?php

declare(strict_types=1);

namespace Switch\Foundation\Testbench;

use LogicException;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

class TestResponse
{
    private ?array $decodedJson = null;

    public function __construct(public readonly ResponseInterface $baseResponse)
    {
    }

    public static function from(ResponseInterface $response): self
    {
        return new self($response);
    }

    public function status(): int
    {
        return $this->baseResponse->getStatusCode();
    }

    public function content(): string
    {
        return (string) $this->baseResponse->getBody();
    }

    /**
     * Get the JSON-decoded response body or a dot-notation subkey.
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($this->decodedJson === null) {
            $this->decodedJson = json_decode($this->content(), true);
            if (!is_array($this->decodedJson)) {
                $this->decodedJson = [];
            }
        }

        if ($key === null) {
            return $this->decodedJson;
        }

        $data = $this->decodedJson;
        foreach (explode('.', $key) as $segment) {
            if (is_array($data) && array_key_exists($segment, $data)) {
                $data = $data[$segment];
            } else {
                return $default;
            }
        }

        return $data;
    }

    public function assertStatus(int $status): self
    {
        $actual = $this->status();
        $this->assertTrue($actual === $status, "Expected status code {$status} but received {$actual}. Response body:\n" . $this->content());
        return $this;
    }

    public function assertOk(): self
    {
        return $this->assertStatus(200);
    }

    public function assertCreated(): self
    {
        return $this->assertStatus(201);
    }

    public function assertNoContent(int $status = 204): self
    {
        return $this->assertStatus($status);
    }

    public function assertNotFound(): self
    {
        return $this->assertStatus(404);
    }

    public function assertForbidden(): self
    {
        return $this->assertStatus(403);
    }

    public function assertUnauthorized(): self
    {
        return $this->assertStatus(401);
    }

    public function assertUnprocessable(): self
    {
        return $this->assertStatus(422);
    }

    public function assertHeader(string $headerName, ?string $value = null): self
    {
        $this->assertTrue(
            $this->baseResponse->hasHeader($headerName),
            "Header [{$headerName}] was not present in the response."
        );

        if ($value !== null) {
            $actual = $this->baseResponse->getHeaderLine($headerName);
            $this->assertTrue(
                str_contains($actual, $value),
                "Header [{$headerName}] has value [{$actual}], expected containing [{$value}]."
            );
        }

        return $this;
    }

    public function assertJson(?array $expected = null): self
    {
        $json = $this->json();
        $this->assertNotEmpty($json, "Response body is not valid JSON or is empty: " . $this->content());

        if ($expected !== null) {
            foreach ($expected as $key => $value) {
                $this->assertJsonPath((string) $key, $value);
            }
        }

        return $this;
    }

    public function assertJsonPath(string $path, mixed $expected): self
    {
        $actual = $this->json($path);
        $this->assertEquals($expected, $actual, "JSON path [{$path}] expected value [" . var_export($expected, true) . "], got [" . var_export($actual, true) . "].");
        return $this;
    }

    public function assertJsonCount(int $count, ?string $key = null): self
    {
        $data = $key !== null ? $this->json($key) : $this->json();
        $actual = is_countable($data) ? count($data) : 0;
        $this->assertEquals($count, $actual, "Expected JSON count {$count}, got {$actual}.");
        return $this;
    }

    public function assertJsonStructure(array $structure, ?array $responseData = null): self
    {
        $data = $responseData ?? $this->json();

        foreach ($structure as $key => $value) {
            if (is_array($value) && $key === '*') {
                $this->assertIsArray($data, "Expected list of items for wildcard [*].");
                foreach ($data as $item) {
                    $this->assertJsonStructure($value, (array) $item);
                }
            } elseif (is_array($value)) {
                $this->assertArrayHasKey($key, $data, "Missing JSON key [{$key}].");
                $this->assertJsonStructure($value, (array) $data[$key]);
            } else {
                $this->assertArrayHasKey($value, $data, "Missing JSON key [{$value}].");
            }
        }

        return $this;
    }

    public function assertSee(string $value, bool $escape = true): self
    {
        $content = $this->content();
        $expected = $escape ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $value;
        $this->assertTrue(str_contains($content, $expected), "Expected to see [{$expected}] in response body.");
        return $this;
    }

    public function assertDontSee(string $value, bool $escape = true): self
    {
        $content = $this->content();
        $expected = $escape ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $value;
        $this->assertFalse(str_contains($content, $expected), "Expected NOT to see [{$expected}] in response body.");
        return $this;
    }

    private function assertTrue(bool $condition, string $message = ''): void
    {
        if (class_exists(Assert::class)) {
            Assert::assertTrue($condition, $message);
        } elseif (!$condition) {
            throw new \Exception("Assertion failed: " . $message);
        }
    }

    private function assertFalse(bool $condition, string $message = ''): void
    {
        $this->assertTrue(!$condition, $message);
    }

    private function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        if (class_exists(Assert::class)) {
            Assert::assertEquals($expected, $actual, $message);
        } elseif ($expected !== $actual) {
            throw new \Exception("Assertion failed: Expected " . var_export($expected, true) . ", got " . var_export($actual, true) . ". " . $message);
        }
    }

    private function assertNotEmpty(mixed $actual, string $message = ''): void
    {
        $this->assertTrue(!empty($actual), $message);
    }

    private function assertIsArray(mixed $actual, string $message = ''): void
    {
        $this->assertTrue(is_array($actual), $message);
    }

    private function assertArrayHasKey(mixed $key, array $array, string $message = ''): void
    {
        $this->assertTrue(array_key_exists($key, $array), $message);
    }
}
