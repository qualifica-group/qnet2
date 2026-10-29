<?php

// Spec 0328, AC-008: no 403 of app/ may be thrown without a reason a person can read.
// The reason is either explicit (a message) or resolved from the denied Gate evaluation
// (Gate::authorize / $this->authorize / Gate::allowIf), never the framework default text.

const FORBIDDEN_STATUS = '(?:403|HttpStatusEnum::FORBIDDEN->value|Response::HTTP_FORBIDDEN)';

/**
 * @return list<string> "relative/path.php:line  snippet" of every offending 403.
 */
function forbiddenWithoutMessage(): array
{
    // One level of nested parentheses is enough for the arguments of an abort call.
    $call = '/\babort(?:_if|_unless)?\(((?:[^()]|\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\))*)\)/s';
    $bare = '/(?:^|,)\s*'.FORBIDDEN_STATUS.'\s*,?\s*$/';
    $framework = '/'.FORBIDDEN_STATUS.'\s*,\s*([\'"])This action is unauthorized\.\1\s*,?\s*$/';
    $emptyException = '/new\s+AuthorizationException\s*(?:\(\s*\))?\s*[;,)]/';

    $violations = [];
    $root = dirname(__DIR__, 3).'/app';

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());
        $relative = substr($file->getPathname(), strlen($root) - 3);

        foreach ([[$call, true], [$emptyException, false]] as [$pattern, $isAbort]) {
            preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as $index => [$text, $offset]) {
                $arguments = $isAbort ? trim($matches[1][$index][0]) : '';

                if ($isAbort && ! preg_match($bare, $arguments) && ! preg_match($framework, $arguments)) {
                    continue;
                }

                $violations[] = $relative.':'.(substr_count(substr($source, 0, $offset), "\n") + 1).'  '.preg_replace('/\s+/', ' ', $text);
            }
        }
    }

    return $violations;
}

it('AC-008: no abort(403) without a message and no AuthorizationException without a message', function () {
    expect(forbiddenWithoutMessage())->toBe([]);
});
