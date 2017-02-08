<?php

namespace AppBundle\Extensions\DataTag;

use AppBundle\Extensions\DataTag\DataTag;
use Topxia\Common\ArrayToolkit;

class SelectedTagGroupsDataTag extends CourseBaseDataTag implements DataTag
{
    public function getData(array $arguments)
    {
        $groupIds = array();

        foreach ($arguments['tags'] as $groupId => $tagId) {
            $groupIds[] = $groupId;
        }

        return $groupIds;
    }

    protected function getTagService()
    {
        return $this->getServiceKernel()->createService('Taxonomy:TagService');
    }
}
