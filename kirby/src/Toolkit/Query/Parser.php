<?php

namespace Kirby\Toolkit\Query;

use Iterator;
use Kirby\Toolkit\Query\AST\ArgumentList;
use Kirby\Toolkit\Query\AST\ArrayList;
use Kirby\Toolkit\Query\AST\Coalesce;
use Kirby\Toolkit\Query\AST\GlobalFunction;
use Kirby\Toolkit\Query\AST\Grouping;
use Kirby\Toolkit\Query\AST\Literal;
use Kirby\Toolkit\Query\AST\MemberAccess;
use Kirby\Toolkit\Query\AST\Node;
use Kirby\Toolkit\Query\AST\Ternary;
use Kirby\Toolkit\Query\AST\Variable;

class Parser extends BaseParser {
    public function __construct(
        Tokenizer|Iterator $source,
    ) {
        parent::__construct($source);
    }

    public function parse(): Node {
        return $this->expression();
    }

    private function expression(): Node {
        return $this->coalesce();
    }

    private function coalesce(): Node {
        $left = $this->ternary();

        while ($this->match(TokenType::COALESCE)) {
            $operator = $this->previous;
            $right = $this->ternary();
            $left = new Coalesce($left, $right);
        }

        return $left;
    }

    private function ternary(): Node {
        $left = $this->memberAccess();

        if ($tok = $this->matchAny([TokenType::QUESTION_MARK, TokenType::TERNARY_DEFAULT])) {
            if($tok->type === TokenType::TERNARY_DEFAULT) {
                $trueIsDefault = true;
                $trueBranch = null;
                $falseBranch = $this->expression();
            } else {
                $trueIsDefault = false;
                $trueBranch = $this->expression();
                $this->consume(TokenType::COLON, 'Expect ":" after true branch.');
                $falseBranch = $this->expression();
            }

            return new Ternary($left, $trueBranch, $falseBranch, $trueIsDefault);
        }

        return $left;
    }

    private function memberAccess(): Node {
        $left = $this->atomic();

        while ($tok = $this->matchAny([TokenType::DOT, TokenType::NULLSAFE])) {
            $nullSafe = $tok->type === TokenType::NULLSAFE;

            $right = $this->consume(TokenType::IDENTIFIER, 'Expect property name after ".".');

            if($this->match(TokenType::OPEN_PAREN)) {
                $arguments = $this->argumentList();
                $left = new MemberAccess($left, $right->lexeme, $arguments, $nullSafe);
            } else {
                $left = new MemberAccess($left, $right->lexeme, null, $nullSafe);
            }
        }

        return $left;
    }

    private function argumentList(): Node {
        if ($this->match(TokenType::CLOSE_PAREN)) {
            return new ArgumentList([]);
        }

        $arguments = [
            $this->expression(),
        ];

        while ($this->match(TokenType::COMMA)) {
            $arguments[] = $this->expression();
        }

        $this->consume(TokenType::CLOSE_PAREN, 'Expect ")" after arguments.');

        return new ArgumentList($arguments);
    }

    private function atomic(): Node {
		// primitives
        if ($token = $this->matchAny([
			TokenType::TRUE,
			TokenType::FALSE,
			TokenType::NULL,
			TokenType::STRING,
			TokenType::NUMBER,
		])) {
            return new Literal($token->literal);
        }

		// array literals
        if ($token = $this->match(TokenType::OPEN_BRACKET)) {
            $expressions = [];

            if (!$this->check(TokenType::CLOSE_BRACKET)) {
                do {
                    $expressions[] = $this->expression();
                } while ($this->match(TokenType::COMMA));
            }

            $this->consume(TokenType::CLOSE_BRACKET, 'Expect "]" after list.');

            return new ArrayList($expressions);
        }

		// global functions and variables
        if ($token = $this->match(TokenType::IDENTIFIER)) {
            if($this->match(TokenType::OPEN_PAREN)) {
                $arguments = $this->argumentList();
                return new GlobalFunction($token->lexeme, $arguments);
            }

            return new Variable($token->lexeme);
        }

		// grouping
        if ($token = $this->match(TokenType::OPEN_PAREN)) {
            $expression = $this->expression();
            $this->consume(TokenType::CLOSE_PAREN, 'Missing ")" after grouping.');
            return $expression;
        }

        throw new \Exception('Expect expression.');
    }
}
