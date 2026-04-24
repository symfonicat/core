<?php

namespace App\Tests\Unit\Entity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Project;
use Symfonicat\Entity\RoutingRule;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Unit coverage for RoutingRule — the entity that owns both invariants we
 * cannot afford to let regress:
 *   1. normalizeScope() zeros the non-applicable FK before persist/update, so
 *      callers can over-populate and the row stays internally consistent.
 *   2. validateArguments() forbids reserved path arguments, which is the
 *      backstop that keeps /admin, /m, and /application from being overridden
 *      by the route-inversion subscriber.
 *
 * We drive validation through a real ValidatorInterface so the
 * #[Assert\Callback] wiring is also exercised.
 */
final class RoutingRuleTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testTypeChoicesExposeBothSupportedTypes(): void
    {
        self::assertSame([
            'domain' => 'domain',
            'project' => 'project',
            'application' => 'application',
            'redirect' => 'redirect',
            'route' => 'route',
        ], RoutingRule::getTypeChoices());
        self::assertSame(['domain', 'project', 'application', 'redirect', 'route'], RoutingRule::getTypes());
        self::assertSame([
            'arguments' => 'arguments',
            'route' => 'route',
        ], RoutingRule::getApplicationTypeChoices());
        self::assertSame([
            'domain' => 'domain',
            'project' => 'project',
            'domain and project' => 'domain_project',
        ], RoutingRule::getRedirectTargetChoices());
    }

    public function testSetTypeRejectsUnknownTypes(): void
    {
        $rule = new RoutingRule();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported routing rule type "bogus"');
        $rule->setType('bogus');
    }

    public function testIsDomainTypeAndIsProjectTypeTrackCurrentType(): void
    {
        $rule = new RoutingRule();

        self::assertTrue($rule->isDomainType(), 'rules default to TYPE_DOMAIN');
        self::assertFalse($rule->isProjectType());

        $rule->setType(RoutingRule::TYPE_PROJECT);
        self::assertFalse($rule->isDomainType());
        self::assertTrue($rule->isProjectType());
    }

    public function testNormalizeScopeClearsProjectWhenRuleIsDomainTyped(): void
    {
        $domain = (new Domain())->setId('example.com');
        $project = (new Project())->setId('project1')->setName('Project 1');

        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_DOMAIN)
            ->setArguments(['blog'])
            ->setDomain($domain)
            ->setProject($project);

        $rule->normalizeScope();

        self::assertSame($domain, $rule->getDomain());
        self::assertNull($rule->getProject(), 'domain-typed rule must drop its project reference');
    }

    public function testNormalizeScopeClearsDomainWhenRuleIsProjectTyped(): void
    {
        $domain = (new Domain())->setId('example.com');
        $project = (new Project())->setId('project1')->setName('Project 1');

        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_PROJECT)
            ->setArguments(['blog'])
            ->setDomain($domain)
            ->setProject($project);

        $rule->normalizeScope();

        self::assertSame($project, $rule->getProject());
        self::assertNull($rule->getDomain(), 'project-typed rule must drop its domain reference');
    }

    public function testNormalizeScopeClearsRouteForArgumentBasedApplicationRules(): void
    {
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_APPLICATION)
            ->setApplicationType(RoutingRule::APPLICATION_TYPE_ARGUMENTS)
            ->setArguments(['symfonicat', '*', 'test*'])
            ->setRoute('app_project_test');

        $rule->normalizeScope();

        self::assertSame(RoutingRule::APPLICATION_TYPE_ARGUMENTS, $rule->getApplicationType());
        self::assertNull($rule->getRoute());
    }

    public function testNormalizeScopeClearsArgumentsForRouteBasedApplicationRules(): void
    {
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_APPLICATION)
            ->setApplicationType(RoutingRule::APPLICATION_TYPE_ROUTE)
            ->setArguments(['symfonicat', '*', 'test*'])
            ->setRoute('app_project_test');

        $rule->normalizeScope();

        self::assertSame([], $rule->getArguments());
        self::assertSame('app_project_test', $rule->getRoute());
    }

    public function testNormalizeScopeKeepsBothRedirectTargetsForCombinedRedirectTarget(): void
    {
        $matchDomain = (new Domain())->setId('example.com');
        $targetDomain = (new Domain())->setId('other.example');
        $project = (new Project())->setId('project2')->setName('Project 2');

        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_REDIRECT)
            ->setRedirectType(RoutingRule::REDIRECT_TYPE_DOMAIN)
            ->setDomain($matchDomain)
            ->setRedirectTarget(RoutingRule::REDIRECT_TYPE_DOMAIN_PROJECT)
            ->setRedirectDomain($targetDomain)
            ->setRedirectProject($project);

        $rule->normalizeScope();

        self::assertSame($targetDomain, $rule->getRedirectDomain());
        self::assertSame($project, $rule->getRedirectProject());
    }

    #[DataProvider('reservedArgumentProvider')]
    public function testValidateArgumentBlocksReservedArguments(string $argument): void
    {
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_DOMAIN)
            ->setDomain((new Domain())->setId('example.com'))
            ->setArguments([$argument]);

        $violations = $this->validator->validate($rule);

        self::assertHasViolationMatching($violations, 'reserved', sprintf('expected "%s" to violate the reserved-argument rule', $argument));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function reservedArgumentProvider(): iterable
    {
        yield 'admin exact match' => ['admin'];
        yield 'admin uppercase' => ['ADMIN'];
        yield 'admin with whitespace' => ['  admin  '];
        yield 'module exact match' => ['m'];
        yield 'module uppercase' => ['M'];
        yield 'module with whitespace' => ['  m  '];
        yield 'application exact match' => ['application'];
        yield 'application uppercase' => ['APPLICATION'];
        yield 'application with whitespace' => ['  application  '];
    }

    public function testValidateArgumentEmitsReservedViolationOnlyOnce(): void
    {
        // Regression guard: the form layer used to add an Assert\Regex with
        // the same message as the entity callback, which rendered the error
        // twice in the admin UI. The entity is the single source of truth.
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_DOMAIN)
            ->setDomain((new Domain())->setId('example.com'))
            ->setArguments(['admin']);

        $violations = $this->validator->validate($rule);

        $reserved = [];
        foreach ($violations as $violation) {
            if (str_contains((string) $violation->getMessage(), 'reserved')) {
                $reserved[] = (string) $violation->getMessage();
            }
        }

        self::assertCount(1, $reserved, sprintf(
            'expected exactly one "reserved" violation, got %d: %s',
            count($reserved),
            implode(' | ', $reserved),
        ));
    }

    public function testValidateArgumentAllowsNonReservedArguments(): void
    {
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_DOMAIN)
            ->setDomain((new Domain())->setId('example.com'))
            ->setArguments(['blog']);

        self::assertCount(0, $this->validator->validate($rule));
    }

    public function testValidateScopeRejectsDomainRuleWithoutDomain(): void
    {
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_DOMAIN)
            ->setArguments(['blog']);

        $violations = $this->validator->validate($rule);

        self::assertHasViolationMatching($violations, 'A domain routing rule requires a domain.');
    }

    public function testValidateScopeRejectsProjectRuleWithoutProject(): void
    {
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_PROJECT)
            ->setArguments(['blog']);

        $violations = $this->validator->validate($rule);

        self::assertHasViolationMatching($violations, 'A project routing rule requires a project.');
    }

    public function testValidateScopeRejectsRouteBasedApplicationRuleWithoutRoute(): void
    {
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_APPLICATION)
            ->setApplicationType(RoutingRule::APPLICATION_TYPE_ROUTE);

        $violations = $this->validator->validate($rule);

        self::assertHasViolationMatching($violations, 'An application routing rule requires an application.');
        self::assertHasViolationMatching($violations, 'A route-based application rule requires a route.');
    }

    public function testValidateScopeRequiresBothRedirectDestinationsForCombinedRedirectTarget(): void
    {
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_REDIRECT)
            ->setRedirectType(RoutingRule::REDIRECT_TYPE_DOMAIN)
            ->setDomain((new Domain())->setId('example.com'))
            ->setRedirectTarget(RoutingRule::REDIRECT_TYPE_DOMAIN_PROJECT);

        $violations = $this->validator->validate($rule);

        self::assertHasViolationMatching($violations, 'A domain and project redirect target requires a redirect domain.');
        self::assertHasViolationMatching($violations, 'A domain and project redirect target requires a redirect project.');
    }

    private static function assertHasViolationMatching(ConstraintViolationListInterface $violations, string $needle, string $context = ''): void
    {
        foreach ($violations as $violation) {
            if (str_contains((string) $violation->getMessage(), $needle)) {
                self::assertTrue(true, $context !== '' ? $context : $needle);
                return;
            }
        }

        self::fail(sprintf(
            '%sNo violation matched "%s". Got: %s',
            $context !== '' ? $context.' — ' : '',
            $needle,
            implode(' | ', array_map(static fn ($v) => (string) $v->getMessage(), iterator_to_array($violations))),
        ));
    }
}
