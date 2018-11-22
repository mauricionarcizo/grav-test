<?php
/**
 * @package    Grav.Common.GPM
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM\Remote;

use Grav\Common\Grav;
use \Doctrine\Common\Cache\FilesystemCache;

class GravCore extends AbstractPackageCollection
{
    protected $repository = 'https://getgrav.org/downloads/grav.json';
    private $data;

    private $version;
    private $date;
    private $min_php;

    /**
     * @param bool $refresh
     * @param null $callback
     * @throws \InvalidArgumentException
     */
    public function __construct($refresh = false, $callback = null)
    {
        $channel = Grav::instance()['config']->get('system.gpm.releases', 'stable');
        $cache_dir   = Grav::instance()['locator']->findResource('cache://gpm', true, true);
        $this->cache = new FilesystemCache($cache_dir);
        $this->repository .= '?v=' . GRAV_VERSION . '&' . $channel . '=1';
        $this->raw = $this->cache->fetch(md5($this->repository));

        $this->fetch($refresh, $callback);

        $this->data    = json_decode($this->raw, true);
        $this->version = isset($this->data['version']) ? $this->data['version'] : '-';
        $this->date    = isset($this->data['date']) ? $this->data['date'] : '-';
        $this->min_php = isset($this->data['min_php']) ? $this->data['min_php'] : null;

        if (isset($this->data['assets'])) {
            foreach ((array)$this->data['assets'] as $slug => $data) {
                $this->items[$slug] = new Package($data);
            }
        }
    }

    /**
     * Returns the list of assets associated to the latest version of Grav
     *
     * @return array list of assets
     */
    public function getAssets()
    {
        return $this->data['assets'];
    }

    /**
     * Returns the changelog list for each version of Grav
     *
     * @param string $diff the version number to start the diff from
     *
     * @return array changelog list for each version
     */
    public function getChangelog($diff = null)
    {
        if (!$diff) {
            return $this->data['changelog'];
        }

        $diffLog = [];
        foreach ((array)$this->data['changelog'] as $version => $changelog) {
            preg_match("/[\w-\.]+/", $version, $cleanVersion);

            if (!$cleanVersion || version_compare($diff, $cleanVersion[0], '>=')) {
                continue;
            }

            $diffLog[$version] = $changelog;
        }

        return $diffLog;
    }

    /**
     * Return the release date of the latest Grav
     *
     * @return string
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * Determine if this version of Grav is eligible to be updated
     *
     * @return mixed
     */
    public function isUpdatable()
    {
        return version_compare(GRAV_VERSION, $this->getVersion(), '<');
    }

    /**
     * Returns the latest version of Grav available remotely
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Returns the minimum PHP version
     *
     * @return null|string
     */
    public function getMinPHPVersion()
    {
        // If non min set, assume current PHP version
        if (is_null($this->min_php)) {
            $this->min_php = phpversion();
        }
        return $this->min_php;
    }

    /**
     * Is this installation symlinked?
     *
     * @return bool
     */
    public function isSymlink()
    {
        return is_link(GRAV_ROOT . DS . 'index.php');
    }
}
