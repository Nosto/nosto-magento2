<?php

namespace Nosto\Tagging\Model;

class Category
{
    /**
     * @var string the category path, e.g. "/Outdoor/Boats/Canoes".
     */
    protected $_path;

    /**
     * Returns the category path, e.g. "/Outdoor/Boats/Canoes".
     *
     * @return string the category path.
     */
    public function getPath()
    {
        return $this->_path;
    }

    /**
     * Sets the category path.
     *
     * @param string $path the new path, e.g. "/Outdoor/Boats/Canoes".
     *
     * @throws \InvalidArgumentException
     */
    public function setPath($path)
    {
        if (!is_string($path) || empty($path)) {
            throw new \InvalidArgumentException(sprintf('%s.path must be a non-empty string value.', __CLASS__));
        }

        $this->_path = $path;
    }
}
