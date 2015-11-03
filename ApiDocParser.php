<?php

namespace machour\yii2\swagger\api;

/**
 * Class ApiDocParser
 *
 * Borrowed with changes from yii\console\Controller
 */
class ApiDocParser {

    /**
     * Returns the first line of doc block.
     *
     * @param string $block
     * @return string
     */
    public static function parseDocCommentSummary($block)
    {
        $docLines = preg_split('~\R~u', $block);
        if (isset($docLines[1])) {
            return trim($docLines[1], "\t *");
        }
        return '';
    }

    /**
     * Returns full description from the doc block.
     *
     * @param string $block
     * @return string
     */
    public static function parseDocCommentDetail($block)
    {
        $comment = strtr(trim(preg_replace('/^\s*\**( |\t)?/m', '', trim($block, '/'))), "\r", '');
        if (preg_match('/^\s*@\w+/m', $comment, $matches, PREG_OFFSET_CAPTURE)) {
            $comment = trim(substr($comment, 0, $matches[0][1]));
        }

        if (empty($comment)) {
            return '';
        }

        $summary = self::parseDocCommentSummary($block);
        $pos = strpos($comment, $summary);
        if ($pos !== false) {
            $comment = trim(substr_replace($comment, '', $pos, strlen($summary)));
        }

        return $comment;
    }

    /**
     * Parses the comment block into tags.
     *
     * @param string $comment the comment block
     * @return array the parsed tags
     */
    public static function parseDocCommentTags($comment)
    {
        $comment = "@description \n" . strtr(trim(preg_replace('/^\s*\**( |\t)?/m', '', trim($comment, '/'))), "\r", '');
        $parts = preg_split('/^\s*@/m', $comment, -1, PREG_SPLIT_NO_EMPTY);
        $tags = [];
//        var_dump($parts);
        foreach ($parts as $part) {
            if (preg_match('/^(\w+)(.*)/ms', trim($part), $matches)) {
                $name = $matches[1];
                if (!isset($tags[$name])) {
                    $tags[$name] = trim($matches[2]);
                } elseif (is_array($tags[$name])) {
                    $tags[$name][] = trim($matches[2]);
                } else {
                    $tags[$name] = [$tags[$name], trim($matches[2])];
                }
            }
        }
        return $tags;
    }
}