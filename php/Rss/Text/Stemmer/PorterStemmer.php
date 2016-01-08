<?php

namespace Rss\Text\Stemmer;

/**
 *
 * @author user
 *
 */
class PorterStemmer
{
    /**
     * Takes a list of words and returns them reduced to their stems.
     *
     * $words can be either a string or an array. If it is a string, it will
     * be split into separate words on whitespace, commas, or semicolons. If
     * an array, it assumes one word per element.
     *
     * @param  mixed $words
     *                            String or array of word(s) to reduce
     * @param  bool  $commonwords
     *                            Remove common words prior to stemming
     * @access public
     * @return array List of word stems
     */
    public function getStemmed($words, $commonwords = false)
    {
        if ($commonwords === true) {
            if (is_array($words)) {
                $words = implode(" ", $words);
                $words = \Rss\Text\CommonWords\CommonWords::removeCommonWords ($words);
                $words = preg_replace('/\s{2,}/', ' ', $words);
                $words = explode(" ", $words);
            }
            if (is_string($words)) {
                $words = \Rss\Text\CommonWords\CommonWords::removeCommonWords ( $words);
                $words = preg_replace('/\s{2,}/', ' ', $words);
            }
        }

        $stemmer = new \Rss\Util\Contrib\Nlp\Stemmers\en\Stemmer\Stemmer ();

        return $stemmer->stem_list ( $words );
    } // function getStemmed($words)

} // class Stemmer
