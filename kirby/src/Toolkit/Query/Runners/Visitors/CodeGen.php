<?php

namespace Kirby\Toolkit\Query\Runners\Visitors;

use Kirby\Toolkit\Query\AST\ArgumentList;
use Kirby\Toolkit\Query\AST\ArrayList;
use Kirby\Toolkit\Query\AST\Coalesce;
use Kirby\Toolkit\Query\AST\GlobalFunction;
use Kirby\Toolkit\Query\AST\Literal;
use Kirby\Toolkit\Query\AST\MemberAccess;
use Kirby\Toolkit\Query\AST\Ternary;
use Kirby\Toolkit\Query\AST\Variable;
use Kirby\Toolkit\Query\Visitor;


/**
 * Visitor that generates code representations from query structures.
 *
 * The `CodeGen` class traverses query nodes and generates corresponding PHP code.
 * It extends the base `Visitor` class, providing implementations specific to code generation.
 */
class CodeGen extends Visitor {

	/**
	 * If we need something from a namespace, we'll add the namespace here into the array key
	 * @var array{string:true}
	 */
	public array $uses = [];

	/**
	 * CodeGen constructor.
	 *
	 * @param array{string:Closure} $validGlobalFunctions An array of valid global function closures.
	 */
	public function __construct(public array $validGlobalFunctions = []){}

	/**
	 * Generates code like `arg1, arg2, arg3` from an argument list node.
	 */
	public function visitArgumentList(ArgumentList $node): string {
		$arguments = array_map(fn($argument) => $argument->accept($this), $node->arguments);
		return join(', ', $arguments);
	}

	/**
	 * Generates code like `[element1, element2, element3]` from an array list node.
	 */
	public function visitArrayList(ArrayList $node): string {
		$elements = array_map(fn($element) => $element->accept($this), $node->elements);
		return '[' . join(', ', $elements) . ']';
	}

	/**
	 * Generates code like `$left ?? $right` from a coalesce node.
	 */
	public function visitCoalesce(Coalesce $node): string {
		$left = $node->left->accept($this);
		$right = $node->right->accept($this);
		return "($left ?? $right)";
	}

	/**
	 * Generates code like `true`, `false`, `123.45`, `"foo bar"`, etc from a literal node.
	 */
	public function visitLiteral(Literal $node): string {
		return var_export($node->value, true);
	}

	/**
	 * Generates code like `$object->member` or `$object->member($arguments)` from a member access node.
	 */
	public function visitMemberAccess(MemberAccess $node): string {
		$object = $node->object->accept($this);
		$member = $node->member;

		if($node->arguments) {
			$op = $node->nullSafe ? '?->' : '->';
			$arguments = $node->arguments->accept($this);
			return "$object$op$member($arguments)";
		}

		$memberStr = var_export($member, true);
		$nullSafe = $node->nullSafe ? 'true' : 'false';

		$this->uses['Kirby\\Toolkit\\Query\\Runtime'] = true;
		return "Runtime::access($object, $memberStr, $nullSafe)";
	}

	/**
	 * Generates code like `($condition ? $trueBranch : $falseBranch)` or `($condition ?: $falseBranch)` from a ternary node.
	 */
	public function visitTernary(Ternary $node): string {
		$left = $node->condition->accept($this);
		$falseBranch = $node->falseBranch->accept($this);

		if($node->trueBranchIsDefault) {
			return "($left ?: $falseBranch)";
		} else {
			$trueBranch = $node->trueBranch->accept($this);
			return "($left ? $trueBranch : $falseBranch)";
		}
	}

	/**
	 * Generates code like `$context['variable'] ?? null` from a variable node.
	 */
	public function visitVariable(Variable $node): string {
		$name = var_export($node->name, true);

		return "(\$context[$name] ?? null)";
	}

	/**
	 * Generates code like `$functions['function']($arguments)` from a global function node.
	 */
	public function visitGlobalFunction(GlobalFunction $node): string {
		$name = $node->name;
		if(!isset($this->validGlobalFunctions[$name])) {
			throw new \Exception("Invalid global function $name");
		}

		$arguments = $node->arguments->accept($this);
		$name = var_export($name, true);

		return "\$functions[$name]($arguments)";
	}

}
