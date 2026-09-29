<?php

namespace Tests\Unit\Services;

use App\DTOs\UploadSummary;
use App\Models\User;
use App\Parsers\StatementParserResolver;
use App\Services\AliasResolutionService;
use App\Services\StatementUploadService;
use App\Services\UsageLimitService;
use Illuminate\Http\UploadedFile;
use ReflectionClass;
use ReflectionNamedType;
use Tests\TestCase;

/**
 * Contrato de assinatura do StatementUploadService (Etapa C §4.2 / expansão §4.1).
 */
class StatementUploadServiceSignatureTest extends TestCase
{
    public function test_constructor_injects_parser_usage_and_aliases(): void
    {
        $ctor = (new ReflectionClass(StatementUploadService::class))->getConstructor();
        $this->assertNotNull($ctor);
        $this->assertSame(3, $ctor->getNumberOfParameters());

        $types = [];
        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();
            $this->assertInstanceOf(ReflectionNamedType::class, $type);
            $types[] = $type->getName();
        }

        $this->assertSame([
            StatementParserResolver::class,
            UsageLimitService::class,
            AliasResolutionService::class,
        ], $types);
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

    public function test_container_resolves_service_with_dependencies(): void
    {
        $service = $this->app->make(StatementUploadService::class);

        $this->assertInstanceOf(StatementUploadService::class, $service);
    }
}
