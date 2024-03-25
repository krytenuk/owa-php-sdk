<?php

namespace OwaSdk\Tracker;
//
// Open Web Analytics - The Open Source Web Analytics Framework
//
// Copyright 2006 Peter Adams. All rights reserved.
//
// Licensed under GPL v2.0 http://www.gnu.org/copyleft/gpl.html
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.
//
// $Id$
//

/**
 * Tracking Event Class
 *
 * @author      Peter Adams <peter@openwebanalytics.com>
 *
 */
class TrackingEvent
{

    /**
     * Event Properties
     *
     * @var array
     */
    var array $properties = [];

    /**
     * State
     *
     * @var string
     */
    //var $state;

    var string $eventType;

    /**
     * Event guid
     *
     * @var string|null
     */
    var ?string $guid;

    /**
     * Creation Timestamp in UNIX EPOC UTC
     *
     * @var int
     */
    var int $timestamp;

    /**
     * Constructor
     * @access public
     */
    public function __construct()
    {

        // Set GUID for event
        $this->guid = $this->setGuid();
        $this->timestamp = time();
        //needed?
        $this->set('guid', $this->guid);
        $this->set('timestamp', $this->timestamp);
    }

    public function getTimestamp(): int
    {

        return $this->timestamp;
    }

    public function set($name, $value): static
    {
        $this->properties[$name] = $value;

        return $this;
    }

    public function get($name)
    {
        if (array_key_exists($name, $this->properties)) {
            return $this->properties[$name];
        } else {
            return false;
        }
    }

    /**
     * Adds new properties to the event without overwriting values
     * for properties that are already set.
     *
     * @param array $properties
     */
    public function setNewProperties(array $properties = []): static
    {
        $this->properties = array_merge($properties, $this->properties);

        return $this;

    }

    /**
     * Create guid from process id
     *
     * @return  string
     * @access private
     */
    public function setGuid(): string
    {
        return $this->generateRandomUid();
    }

    private function generateRandomUid(): string
    {
        $time = (string)time();
        $random = $this->zeroFill(mt_rand(0, 999999));

        $server = substr(getmypid(), 0, 3);


        return $time . $random . $server;
    }

    private function zeroFill($number): string
    {
        return str_pad((int)$number, 6, "0", STR_PAD_LEFT);
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getEventType()
    {
        if (!empty($this->eventType)) {
            return $this->eventType;
        } elseif ($this->get('event_type')) {
            return $this->get('event_type');
        } else {

            return 'unknown_event_type';
        }
    }

    public function setEventType($value): static
    {
        $this->eventType = $value;

        return $this;
    }

    public function getGuid(): ?string
    {
        return $this->guid;
    }

    // move this to the tracker
    public function getSiteSpecificGuid(): string
    {
        return $this->generateRandomUid();
    }

}
