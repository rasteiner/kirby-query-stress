<?php

namespace Kirby\Toolkit\Query\Runners\Visitors;

use Exception;
use Kirby\Toolkit\Query\AST\ArgumentList;
use Kirby\Toolkit\Query\AST\ArrayList;
use Kirby\Toolkit\Query\AST\Coalesce;
use Kirby\Toolkit\Query\AST\Literal;
use Kirby\Toolkit\Query\AST\MemberAccess;
use Kirby\Toolkit\Query\AST\Ternary;
use Kirby\Toolkit\Query\AST\Variable;
use Kirby\Toolkit\Query\AST\GlobalFunction;
use Kirby\Toolkit\Query\Runtime;
use Kirby\Toolkit\Query\Visitor;


/**
 * Visitor that interprets and directly executes a query AST.
 */
class Interpreter extends Visitor {
	/**
	 * @param array{string:Closure} $validGlobalFunctions An array of valid global function closures.
	 * @param array{string:mixed} $context The data bindings for the query.
	 */
	public function __construct(
		public array $validGlobalFunctions = [],
		public array $context = []
	) {}

    public function visitArgumentList(ArgumentList $node): mixed {
		return array_map(fn($argument) => $argument->accept($this), $node->arguments);
	}

    public function visitArrayList(ArrayList $node): mixed {
		return array_map(fn($element) => $element->accept($this), $node->elements);
	}

    public function visitCoalesce(Coalesce $node): mixed {
		return $node->left->accept($this) ?? $node->right->accept($this);
	}

    public function visitLiteral(Literal $node): mixed {
		return $node->value;
	}

    public function visitMemberAccess(MemberAccess $node): mixed {
		if($node->arguments !== null) {
			$left = $node->object->accept($this);
			if($node->nullSafe) {
				return $left?->{$node->member}(...$node->arguments->accept($this));
			} else {
				return $left->{$node->member}(...$node->arguments->accept($this));
			}
		}
		return Runtime::access($node->object->accept($this), $node->member, $node->nullSafe);
	}

    public function visitTernary(Ternary $node): mixed {
		if($node->trueBranchIsDefault) {
			return $node->condition->accept($this) ?: $node->trueBranch->accept($this);
		} else {
			return $node->condition->accept($this) ? $node->trueBranch->accept($this) : $node->falseBranch->accept($this);
		}
	}

    public function visitVariable(Variable $node): mixed {
		return $this->context[$node->name] ?? null;
	}

    public function visitGlobalFunction(GlobalFunction $node): mixed {
		if(!isset($this->validGlobalFunctions[$node->name])) {
			throw new Exception("Invalid global function $node->name");
		}
		return $this->validGlobalFunctions[$node->name](...$node->arguments->accept($this));
	}
}
