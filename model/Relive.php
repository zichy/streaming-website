<?php

class Relive extends ModelBase
{
	public function isEnabled()
	{
		// having CONFERENCE.RELIVE is not enough!
		return $this->has('CONFERENCE.RELIVE_JSON');
	}

	public function getTalks()
	{
		if($talks_by_id = $this->getCached())
			return $talks_by_id;

		$talks = file_get_contents($this->get('CONFERENCE.RELIVE_JSON'));
		$talks = utf8_decode($talks);
		$talks = (array)json_decode($talks, true);

		usort($talks, function($a, $b) {
			$sort = array('live', 'recorded', 'released');
			return array_search($a['status'], $sort) > array_search($b['status'], $sort);
		});

		$talks_by_id = array();
		foreach ($talks as $talk)
		{
			if($talk['status'] == 'released')
				$talk['url'] = $talk['release_url'];
			else
				$talk['url'] = 'relive/'.rawurlencode($talk['id']).'/';

			$talks_by_id[$talk['id']] = $talk;
		}

		return $this->doCache($talks_by_id);
	}

	public function getTalk($id)
	{
		$talks = $this->getTalks();
		if(!isset($talks[$id]))
			throw new NotFoundException('Relive-Talk id '.$id);

		return $talks[$id];
	}

	private function isCacheEnabled()
	{
		return $this->has('CONFERENCE.RELIVE_JSON_CACHE') && function_exists('apc_fetch') && function_exists('apc_store');
	}

	private function getCacheDuration()
	{
		return $this->get('CONFERENCE.RELIVE_JSON_CACHE', 60*10 /* 10 minutes */);
	}

	private $localCache = null;
	private function getCached()
	{
		if($this->localCache)
			return $this->localCache;

		if(!$this->isCacheEnabled())
			return null;

		return apc_fetch('RELIVE.CACHE');
	}

	private function doCache($value)
	{
		$this->localCache = $value;

		if(!$this->isCacheEnabled())
			return $value;

		apc_store('RELIVE.CACHE', $value, $this->getCacheDuration());
		return $value;
	}
}
