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

namespace local_mymedia\output;

use moodle_url;

class zoom_media_tabs implements \renderable, \templatable {
    private moodle_url $url;
    private bool $canviewchannels;

    public function __construct($url, $canviewchannels) {
        $this->url = $url;
        $this->canviewchannels = $canviewchannels;
    }

    public function export_for_template(\core\output\renderer_base $output) {
        $activetab = $this->url->get_param('activetab');
        $tabs = ['videos', 'channels', 'clips', 'record', 'upload','kaltura'];

        $data = new \stdClass();

        $data->tabs = [];
        foreach ($tabs as $tab) {
            if ($tab == 'channels' && !$this->canviewchannels) {
                continue;
            }

            $data->tabs[] = (object)[
                'name' => get_string("tab_{$tab}", 'local_mymedia'),
                'active' => $tab === $activetab,
                'url' => $this->get_tab_url($tab)
            ];
        }

        return $data;
    }

    protected function get_tab_url($tab): moodle_url {
        $url = new moodle_url($this->url);
        $url->param('activetab', $tab);
        return $url;
    }
}
