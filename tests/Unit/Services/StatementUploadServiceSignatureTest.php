<?php

namespace Tests\Unit\Services;

use App\DTOs\UploadSummary;
use App\Models\User;
use App\Parsers\StatementParserResolver;
use App\Services\StatementUploadService;
use Illuminate\Http\UploadedFile;
use ReflectionClass;
use ReflectionNamedType;
use Tests\TestCase;

/**
 * §4.2 — contrato de assinatura do StatementUploadService.
 */
class StatementUploadServiceSignatureTest extends TestCase
{
    public function test_constructor_injects_statement_parser_resolver(): void
    {
        $ctor = (new ReflectionClass(StatementUploadService::class))->getConstructor();
        $this->assertNotNull($ctor);
        $this->assertSame(1, $ctor->getNumberOfParameters());

        $param = $ctor->getParameters()[0];
        $type = $param->getType();
        $this->assertInstanceOf(ReflectionNamedType::class, $type);
        $this->assertSame(StatementParserResolver::class, $type->getName());
    }

    public function test_handle_signature_matches_plan_contract(): void
    {
        $method = (new ReflectionClass(StatementUploadService::class))->getMethod('handle');
        $params = $method->getParameters();

        $this->assertCount(3, $params);

        $this->assertSame('user', $params[0]->getName());
        $this->assertSame(User::class, $params[0]->getType()->getName());

        $this->assertSame('file', $params[1]->getName());
        $this->assertSame(UploadedFile::class, $params[1]->getType()->getName());

        $this->assertSame('source', $params[2]->getName());
        $this->assertSame('string', $params[2]->getType()->getName());
        $this->assertTrue($params[2]->isDefaultValueAvailable());
        $this->assertSame('nubank', $params[2]->getDefaultValue());

        $return = $method->getReturnType();
        $this->assertInstanceOf(ReflectionNamedType::class, $return);
        $this->assertSame(UploadSummary::class, $return->getName());
    }

    public function test_container_resolves_service_with_resolver(): void
    {
        $service = $this->app->make(StatementUploadService::class);

        $this->assertInstanceOf(StatementUploadService::class, $service);
    }
}
