<?php

namespace Etshy\Rector8237\Rule;

use PhpParser\Node;
use Rector\Core\Contract\Rector\ConfigurableRectorInterface;
use Rector\Core\Rector\AbstractRector;
use Rector\Naming\Naming\UseImportsResolver;
use Symplify\RuleDocGenerator\Exception\PoorDocumentationException;
use Symplify\RuleDocGenerator\Exception\ShouldNotHappenException;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * The rule is far from perfect but fit the needs I have for now.
 */
class MyCustomRule extends AbstractRector  implements ConfigurableRectorInterface
{

    private array $configurations = [];

    /**
     * @param UseImportsResolver $useImportsResolver
     */
    public function __construct(
        private readonly UseImportsResolver $useImportsResolver,
    ) {
    }

    /**
     * @return RuleDefinition
     * @throws PoorDocumentationException
     * @throws ShouldNotHappenException
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition("", [new ConfiguredCodeSample('', '', [])]);
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [Node\AttributeGroup::class];
    }

    /**
     * @param Node $node
     * @return null
     */
    public function refactor(Node $node)
    {
        $uses = $this->useImportsResolver->resolveBareUses();
        foreach ($uses as $use) {
            foreach ($use->uses as $useUse) {
                foreach ($this->configurations as $key => $configuration) {
                    if (!str_contains($configuration[0], $useUse->name->toString())) {
                        //class or namespace not imported
                        continue;
                    }

                    if ($useUse->alias && str_contains($configuration[0], $useUse->name->toString())) {
                        $alias = $useUse->alias->toString();
                        $this->configurations[$key]['alias'] = $alias;
                    }
                }
            }
        }

        foreach ($node->attrs as $keyAttr => $attr) {
            foreach ($attr->args as $key => $arg) {
                $hasChanged = $this->processArgs($arg);

                if ($hasChanged) {
                    continue;
                }

                if ($arg->value instanceof Node\Expr\Array_) {
                    foreach ($arg->value->items as $keyItem => $item) {
                        if (!is_array($item->value->args)) {
                            continue;
                        }
                        foreach ($item->value->args as $key2 => $arg2) {
                            $this->processArgs($arg2);
                            $item->value->args[$key2] = $arg2;
                        }
                        $arg->value->items[$keyItem] = $item;
                    }
                }
                if ($arg->value instanceof Node\Arg) {
                    $this->processArgs($arg->value);
                }
                $attr->args[$key] = $arg;
            }
            $node->attrs[$keyAttr] = $attr;
        }

        return null;
    }

    /**
     * @param Node\Arg $arg
     * @param Node\Stmt\Use_[] $uses
     * @return bool
     */
    private function processArgs(Node\Arg $arg): bool
    {
        $hasChanged = false;

        if ($arg->value instanceof Node\Expr\New_) {
            foreach ($this->configurations as $configuration) {
                if (
                    !str_contains($configuration[0], $arg->value->class->toString())
                    && (
                        !isset($configuration['alias'])
                        || !str_starts_with($arg->value->class->toString(), $configuration['alias'].'\\')
                    )
                ) {
                    //class or namespace not imported
                    continue;
                }

                if (isset($configuration['alias'])) {
                    //alias found

                    $currentArgClassShortName = end($arg->value->class->parts);
                    $configurationClassNameExploded = explode('\\', $configuration[0]);
                    $currentConfigClassSHortName = end($configurationClassNameExploded);

                    if ($currentArgClassShortName !== $currentConfigClassSHortName) {
                        //same alias (could be namespace alias ?) but not the same class
                        continue;
                    }

                    $arg->name = new Node\Identifier($configuration[1]);
                    $hasChanged = true;
                } else {
                    //no alias, just look for the class name
                    $arrayClassName = explode("\\", $configuration[0]);
                    if (end($arg->value->class->parts) === end($arrayClassName)) {
                        $arg->name = new Node\Identifier($configuration[1]);
                        $hasChanged = true;
                    }
                }
            }
        }

        return $hasChanged;
    }

    /**
     * @param array $configuration
     * @return void
     */
    public function configure(array $configuration): void
    {
        $this->configurations = $configuration;
    }
}