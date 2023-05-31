<?php 

/**
 * Lenevor Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file license.md.
 * It is also available through the world-wide-web at this URL:
 * https://lenevor.com/license
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@Lenevor.com so we can send you a copy immediately.
 *
 * @package     Lenevor
 * @subpackage  Base
 * @link        https://lenevor.com
 * @copyright   Copyright (c) 2019 - 2023 Alexander Campo <jalexcam@gmail.com>
 * @license     https://opensource.org/licenses/BSD-3-Clause New BSD license or see https://lenevor.com/license or see /license.md
 */

namespace Syscodes\Components\Routing\Generator;

use LogicException;
use DomainException;
use InvalidArgumentException;
use Syscodes\Components\Support\Arr;
use Syscodes\Components\Support\Str;
use Syscodes\Components\Routing\Route;
use Syscodes\Components\Routing\Collections\CompiledRouteCollection;

/**
 * Allows compile the route patterns.
 */
class RouteCompiler
{
    /**
     * This string defines which separators will be in optional placeholders 
     * for matching and generating URLs.
     */
    public const SEPARATOR = '/,;.:-_~+*=@';

    /**
     * The maximum supported length.
     */
    public const VARIABLE_MAXMUM_LENGTH = 32;
    
    /**
     * Compile the inner Route pattern.
     * 
     * @param  \Syscodes\Components\Routing\Route  $route
     * 
     * @return string
     * 
     * @throws \LogicException|\DomainException|\InvalidArgumentException
     */
    public static function compile(Route $route): CompiledRouteCollection
    {
        $hostVariables = [];
        $variables = [];
        $hostRegex = null;
        $hostTokens = [];

        if ('' !== $host = $route->getHost()) {
            $result = self::compilePattern($route, $host, true);

            $hostVariables = $result['variables'];
            $variables = $hostVariables;

            $hostTokens = $result['tokens'];
            $hostRegex = $result['regex'];
        }

        $path = $route->getRoute();

        $result = static::compilePattern($route, $path, false);

        $staticPrefix = $result['staticPrefix'];

        $pathVariables = $result['variables'];

        foreach ($pathVariables as $pathParam) {
            if ('_fragment' === $pathParam) {
                throw new InvalidArgumentException(
                    sprintf('Route pattern "%s" cannot contain "_fragment" as a path parameter.', $route->getRoute())
                );
            }
        }

        $variables = array_merge($variables, $pathVariables);

        $tokens = $result['tokens'];
        $regex = $result['regex'];

        return new CompiledRouteCollection(
            $staticPrefix,
            $regex,
            $tokens,
            $pathVariables,
            $hostRegex,
            $hostTokens,
            $hostVariables,
            array_unique($variables)
        );
    }

    /**
     * The compile pattern for iterate over variables in the routes.
     * 
     * @param  \Syscodes\Components\Routing\Route  $route
     * @param  string|null  $pattern
     * @param  bool  $isHost
     * 
     * @return array
     */
    private static function compilePattern(Route $route, ?string $pattern, bool $isHost): array
    {
        $tokens           = [];
        $variables        = [];
        $pos              = 0;
        $defaultSeparator = $isHost ? '.' : '/';
        $useUtf8          = preg_match('//u', $pattern);

        if ($useUtf8 && preg_match('~[\x80-\xFF]~', $pattern)) {
            throw new LogicException(
                sprintf('Cannot use UTF-8 route patterns without setting the "utf8" option for route "%s".', $route->getRoute())
            );
        }

        if ( ! $useUtf8) {
            throw new LogicException(
                sprintf('Cannot mix UTF-8 requirements with non-UTF-8 pattern "%s"', $pattern)
            );
        }

        preg_match_all('~\{(!)?([\w\x80-\xFF]+)\}~', $pattern, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        foreach ($matches as $match) {
            $main = $match[1][1] >= 0;
            $varName = $match[2][0];
            $precedingText = substr($pattern, $pos, $match[0][1] - $pos);
            $pos = $match[0][1] + strlen($match[0][0]);

            if ( ! strlen($precedingText)) {
                $precedingChar = '';
            } elseif ($useUtf8) {
                preg_match('/.$/u', $precedingText, $precedingChar);

                $precedingChar = $precedingChar[0];
            } else {
                $precedingChar = substr($precedingText, -1);
            }

            $separator = '' != $precedingChar && Str::contains(static::SEPARATOR, $precedingChar);

            if (preg_match('~^\d~', $varName) === 1) {
                throw new DomainException(
                    sprintf('Variable name "%s" cannot start with a digit in route pattern "%s"', $varName, $pattern)
                );
            }

            if (in_array($varName, $variables)) {
                throw new LogicException(
                    sprintf('Route pattern "%s" cannot reference variable name "%s" more than once', $pattern, $varName)
                );
            }
            
            if (strlen($varName) > self::VARIABLE_MAXMUM_LENGTH) {
                throw new DomainException(
                    sprintf('Variable name "%s" cannot be longer than %d characters in route pattern "%s"', $varName, self::VARIABLE_MAXMUM_LENGTH, $pattern)
                );
            }
            
            if ($separator && $precedingText !== $precedingChar) {
                $tokens[] = ['text', substr($precedingText, 0, -strlen($precedingChar))];
            } elseif ( ! $separator && '' !== $precedingText) {
                $tokens[] = ['text', $precedingText];
            }
            
            $regex = $route->setPattern($varName);
            
            if (null === $regex) {
                $following = (string) substr($pattern, $pos);

                $nextSeparator = static::findNextStaticSeparator($following, $useUtf8);

                $regex = sprintf(
                    '[^%s%s]+',
                    preg_quote($defaultSeparator),
                    $defaultSeparator !== $nextSeparator && '' !== $nextSeparator ? preg_quote($nextSeparator) : ''
                );

                if (('' !== $nextSeparator && ! preg_match('~^\{[\w\x80-\xFF]+\}~', $following)) || '' === $following) {
                    $regex .= '+';
                }
            } else {
                if ( ! preg_match('//u', $regex)) {
                    $useUtf8 = false;
                }

                $regex = static::groupsToNonCapturings($regex);
            }
            
            if ($main) {
                $token = ['variable', $separator ? $precedingChar : '', $regex, $varName, false, true];
            } else {
                $token = ['variable', $separator ? $precedingChar : '', $regex, $varName];
            }
            
            $tokens[]    = $token;
            $variables[] = $varName;
        }
        
        if ($pos < strlen($pattern)) {
            $tokens[] = ['text', substr($pattern, $pos)];
        }
        
        // find the first optional token
        $firstOptional = PHP_INT_MAX;
        
        // compute the matching regexp
        $regex = '';

        for ($i = 0, $nbToken = count($tokens); $i < $nbToken; ++$i) {
            $regex .= static::CheckTokenRegex($tokens, $i, $firstOptional);
        }

        $regex = '{^/'.$regex.'$}sD'.($isHost ? 'i' : '');

        return [
            'staticPrefix' => $regex,
            'regex' => $regex,
            'tokens' => array_reverse($tokens),
            'variables' => $variables,
        ];
    }

    /**
     * Returns the next static character in the Route pattern that will serve as a separator.
     * 
     * @param  string  $pattern
     * @param  bool  $useUtf8
     * 
     * @return string
     */
    private static function findNextStaticSeparator(string $pattern, bool $useUtf8): string
    {
        if ('' === $pattern) {
            return '';
        }

        if ('' === $pattern = preg_replace('~\{[\w\x80-\xFF]+\}~', '', $pattern)) {
            return '';
        }

        if ($useUtf8) {
            preg_match('/^./u', $pattern, $pattern); 
        }

        return Str::contains(self::SEPARATOR, $pattern[0]) ? $pattern[0] : '';
    }
    
    /**
     * Checks the regex used to match a specific token. It can be static text or a subpattern.
     * 
     * @param  array  $tokens  The route tokens
     * @param  int  index  The index of the current token
     * @param  int  $firstOptional  The index of the first optional token
     * 
     * @return string
     */
    private static function CheckTokenRegex(array $tokens, int $index, int $firstOptional): string
    {
        $token = $tokens[$index];
        
        if ('text' === $token[0]) {
            // Text tokens
            return preg_quote($token[1]);
        } else {
            // Variable tokens
            if (0 === $index && 0 === $firstOptional) {
                // When the only token is an optional variable token, the separator is required
                return sprintf('%s(?P<%s>%s)?', preg_quote($token[1]), $token[3], $token[2]);
            } else {
                $regexp = sprintf('%s(?P<%s>%s)', preg_quote($token[1]), $token[3], $token[2]);
                
                if ($index >= $firstOptional) {
                    $regexp = "(?:$regexp";
                    $nbTokens = count($tokens);
                    
                    if ($nbTokens - 1 == $index) {
                        // Close the optional subpatterns
                        $regexp .= str_repeat(')?', $nbTokens - $firstOptional - (0 === $firstOptional ? 1 : 0));
                    }
                }
                
                return $regexp;
            }
        }
    }

    /**
     * The groups non capturings of regex.
     * 
     * @param  string  $regex
     * 
     * @return string
     */    
    private static function groupsToNonCapturings(string $regex): string
    {
        for ($i = 0; $i < strlen($regex); ++$i) {
            if ('\\' === $regex[$i]) {
                ++$i;
                continue;
            }
            
            if ('(' !== $regex[$i] || ! isset($regex[$i + 2])) {
                continue;
            }
            
            if ('*' === $regex[++$i] || '?' === $regex[$i]) {
                ++$i;
                continue;
            }
            
            $regex = substr_replace($regex, '?:', $i, 0);
            ++$i;
        }
        
        return $regex;
    }
}