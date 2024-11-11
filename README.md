
# Query compilation experiment

This install provides two new alternative query "runners". You can enable them in your `site/config/config.php`:

```
return [
    // uncomment one of the following lines
    // 'query.runner' => 'transpiled',
    // 'query.runner' => 'interpreted',
    // 'query.runner' => 'legacy', // default
];
```

Interpreted queries make use of an abstract syntax tree (AST) to evaluate the query.
Transpiled queries transpile the AST into PHP code that is then executed. 

The legacy runner is the original query runner that directly executes the query string.

There is a test dataset in content. Log into the panel and click on a "Person" page to run a slow query.
Compare the performance of the different runners. The two new runners should be around ~30% faster than the legacy runner.

## Affected files

There is a new `Kirby\Toolkit\Query` namespace which contains all logic for the new query runners.
The general process is as follows:
  1. The query string is split into a flat sequence of tokens (see [`Kirby\Toolkit\Query\Tokenizer`](https://github.com/rasteiner/kirby-query-stress/blob/main/kirby/src/Toolkit/Query/Tokenizer.php)).
  2. The tokens are parsed into a recursive abstract syntax tree (see [`Kirby\Toolkit\Query\Parser`](https://github.com/rasteiner/kirby-query-stress/blob/main/kirby/src/Toolkit/Query/Parser.php)).
  3. The AST is then [visited](https://en.wikipedia.org/wiki/Visitor_pattern) by
     - an interpreter ([`Kirby\Toolkit\Query\Runners\Visitors\Interpreter`](https://github.com/rasteiner/kirby-query-stress/blob/main/kirby/src/Toolkit/Query/Runners/Visitors/Interpreter.php)) that evaluates the query, or
     - a code generator ([`Kirby\Toolkit\Query\Runners\Visitors\CodeGen`](https://github.com/rasteiner/kirby-query-stress/blob/main/kirby/src/Toolkit/Query/Runners/Visitors/CodeGen.php)) that transpiles it into PHP code.

The whole process and the caching is handled by the two "runner" classes:
  - [`Kirby\Toolkit\Query\Runners\Interpreted`](https://github.com/rasteiner/kirby-query-stress/blob/main/kirby/src/Toolkit/Query/Runners/Interpreted.php)
  - [`Kirby\Toolkit\Query\Runners\Transpiled`](https://github.com/rasteiner/kirby-query-stress/blob/main/kirby/src/Toolkit/Query/Runners/Transpiled.php)
  
Separating the parsing and execution steps allows for more flexibility and better performance, since it allows us to cache the AST.

The original `Kirby\Query\Query` class remains in place and chooses which runner to use based on the `query.runner` option.

The `Kirby\Query\Query::intercept()` method has been marked as deprecated as it couldn't support runners based on an AST.
