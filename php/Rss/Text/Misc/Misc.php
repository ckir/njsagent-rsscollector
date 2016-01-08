<?php

namespace Rss\Text\Misc;

/**
 *
 * @author user
 *
 */
class Misc
{
    /**
     * Calculate the similarity between two strings
     *
     * @param  string $first
     *                             First string
     * @param  string $second
     *                             Second string
     * @param  bool   $commonwords
     *                             Remove common words prior to calculation
     * @param  bool   $stemming
     *                             Apply stemming to strings prior to calculation.
     * @return array
     */
    public function getSimilarText($first, $second, $commonwords = false, $stemming = false)
    {
        if ($commonwords === true) {
            $first = \Rpc\Text\CommonWords\CommonWords::removeCommonWords ( $first );
            $second = \Rpc\Text\CommonWords\CommonWords::removeCommonWords ( $second );
        }
        if ($stemming === true) {
            $stemmer = new \Rpc\Text\Stemmer\PorterStemmer ();
            $first = $stemmer->getStemmed ( $first );
            $first = implode(" ", $first);
            $second = $stemmer->getStemmed ( $second );
            $second  = implode(" ", $second );
        }

        $matching_chars = similar_text ( $first, $second, $percent );

        return array (
                'matchingChars' => $matching_chars,
                'percent' => $percent
        );
    } // function getSimilarText()

    /**
     * Calculate the metaphone keys of strings in a array
     *
     * @param  array $strings
     *                            Array of the input strings.
     * @param  bool  $commonwords
     *                            Remove common words prior to calculation
     * @param  bool  $stemming
     *                            Apply stemming to strings prior to calculation.
     * @return array
     */
    public function getMetaphone($strings, $commonwords = false, $stemming = false)
    {
        $response = array();
        if ($stemming === true) {
            $stemmer = new \Rss\Text\Stemmer\PorterStemmer();
        }
        for ($i = 0; $i < count ( $strings ); $i ++) {
            if ($commonwords === true) {
                $strings [$i] = \Rss\Text\CommonWords\CommonWords::removeCommonWords ( $strings [$i] );
            }
            if ($stemming === true) {
                $strings [$i] = $stemmer->getStemmed ( $strings [$i] );
                $strings [$i] = implode(" ", $strings [$i]);
            }
            if (! empty($strings [$i])) {
                $response [$strings [$i]] = metaphone ( $strings [$i] );
            }
        }

        return $response;
    } // function getMetaphone
} // class Misc
