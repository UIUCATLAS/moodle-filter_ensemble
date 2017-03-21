<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**
 * Ensemble Video filter plugin.
 *
 * @package    filter_ensemble
 * @copyright  2012 Liam Moran, Nathan Baxley, University of Illinois
 *             2013 Symphony Video, Inc.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(dirname(dirname(__FILE__))) . '/repository/lib.php');

class filter_ensemble extends moodle_text_filter {

    private $ensembleurl;

    public function filter($text, array $options = array()) {
        global $CFG;

        if (!is_string($text) or empty($text)) {
            // Performance imporovement - can't filter non-strings or empty text
            return $text;
        }

        if (stripos($text, '</a>') == false) {
            // Performance improvement - nothing to match
            return $text;
        }

        $newtext = $text;

        if (!isset($CFG->filter_ensemble_urls)) {
            set_config('filter_ensemble_urls', '');
        }

        $urls = explode(',', $CFG->filter_ensemble_urls);

        $repos = repository::get_instances(array(
            'currentcontext' => $this->context,
            'type' => 'ensemble'
        ));

        // Add the currently configured repository urls for this context.
        foreach ($repos as $repo) {
            $urls[] = $repo->get_option('ensembleURL');
        }

        foreach ($urls as $url) {
            $this->ensembleUrl = trim($url);
            if (stripos($text, $this->ensembleUrl) === false) {
                // Performance improvement - no matching URL
                continue;
            }
            $search = '#<a [^>]*href="' . $this->ensembleUrl . '\?([^"]*)".*</a>#isU';
            $newtext = preg_replace_callback($search, array('filter_ensemble', 'callback'), $newtext);
        }

        if (is_null($newtext) or $newtext === $text) {
            return $text;
        }

        return $newtext;
    }

    private function callback($matches) {
        $settings = array();
        $urldecoded = urldecode($matches[1]);
        $entitydecoded = html_entity_decode($urldecoded);
        parse_str($entitydecoded, $settings);
        if (isset($settings['type'])) {
            if ($settings['type'] === 'video') {
                $width = isset($settings['width']) ? $settings['width'] : 640;
                $height = isset($settings['height']) ? $settings['height'] : 360;
                $source = $this->ensembleUrl . '/app/plugin/embed.aspx?ID=' . $settings['id'] . '&autoPlay='
                . $settings['autoplay'] . '&displayTitle=' . $settings['showtitle'] . '&hideControls=' . $settings['hidecontrols']
                . '&showCaptions=' . $settings['showcaptions'] . '&width=' . $width . '&height=' . $height;
                return '<iframe src="' . $source . '" frameborder="0" style="width: ' . $width . 'px;height:' . ($height + 56)
                . 'px;" allowfullscreen></iframe>';
            } else if ($settings['type'] === 'playlist') {
                $source = $this->ensembleUrl . '/app/plugin/embed.aspx?DestinationID=' . $settings['id'];
                return '<iframe src="' . $source . '" frameborder="0" style="width:800px;height:1000px;" allowfullscreen></iframe>';
            }
        } else if (isset($settings['content'])) {
            return $settings['content'];
        }
    }

}
