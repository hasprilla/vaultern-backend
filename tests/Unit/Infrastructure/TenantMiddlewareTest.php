<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Http\Middleware\TenantMiddleware;
use PHPUnit\Framework\TestCase;

class TenantMiddlewareTest extends TestCase
{
    public function test_middleware_class_exists(): void
    {
        $this->assertTrue(class_exists(TenantMiddleware::class));
    }

    public function test_middleware_has_handle_method(): void
    {
        $this->assertTrue(method_exists(TenantMiddleware::class, 'handle'));
    }

    public function test_handle_method_accepts_request_and_closure(): void
    {
        $reflection = new \ReflectionMethod(TenantMiddleware::class, 'handle');
        $params = $reflection->getParameters();
        $this->assertCount(2, $params);
        $this->assertSame('request', $params[0]->getName());
        $this->assertSame('next',    $params[1]->getName());
    }

    public function test_handle_return_type_is_mixed(): void
    {
        $reflection = new \ReflectionMethod(TenantMiddleware::class, 'handle');
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('mixed', (string) $returnType);
    }
}
