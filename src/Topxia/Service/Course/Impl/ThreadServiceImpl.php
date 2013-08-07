<?php
namespace Topxia\Service\Course\Impl;

use Topxia\Service\Common\BaseService;
use Topxia\Service\Course\ThreadService;
use Topxia\Common\ArrayToolkit;

class ThreadServiceImpl extends BaseService implements ThreadService
{

	public function getThread($courseId, $threadId)
	{
		$thread = $this->getThreadDao()->getThread($threadId);
		if (empty($thread)) {
			return null;
		}
		return $thread['courseId'] == $courseId ? $thread : null;
	}

	public function findThreadsByType($courseId, $type, $sort = 'latestCreated', $start, $limit)
	{
		if ($sort == 'latestPosted') {
			$orderBy = array('latestPosted', 'DESC');
		} else {
			$orderBy = array('createdTime', 'DESC');
		}

		if (!in_array($type, array('question', 'discussion'))) {
			$type = 'all';
		}

		if ($type == 'all') {
			return $this->getThreadDao()->findThreadsByCourseId($courseId, $orderBy, $start, $limit);
		}

		return $this->getThreadDao()->findThreadsByCourseIdAndType($courseId, $type, $orderBy, $start, $limit);
	}

    public function deleteThreadsByIds(array $ids=null){

        if(empty($ids)){
             throw $this->createServiceException("Please select thread item !");
        }

       	foreach ($ids as $id) {
            $this->getThreadDao()->deleteThread($id);
            $this->getThreadPostDao()->deletePostsByThreadId($id);
        }
        return true;
    }

	public function searchThreads($conditions, $sort, $start, $limit)
	{
		switch ($sort) {
			case 'created':
				$orderBys = array(
					array('isStick', 'DESC'),
					array('createdTime', 'DESC'),
				);
				break;
			case 'posted':
				$orderBys = array(
					array('isStick', 'DESC'),
					array('latestPostTime', 'DESC'),
				);
				break;
			case 'createdNotStick':
				$orderBys = array(
					array('createdTime', 'DESC'),
				);
				break;
			case 'postedNotStick':
				$orderBys = array(
					array('latestPostTime', 'DESC'),
				);
				break;
			default:
				throw $this->createServiceException('参数sort不正确。');
		}
		return $this->getThreadDao()->searchThreads($conditions, $orderBys, $start, $limit);
	}

	public function searchThreadCount($conditions)
	{
		return $this->getThreadDao()->searchThreadCount($conditions);
	}

	public function createThread($thread)
	{
		if (empty($thread['courseId'])) {
			throw $this->createServiceException('Course ID can not be empty.');
		}
		if (empty($thread['type']) or !in_array($thread['type'], array('discussion', 'question'))) {
			throw $this->createServiceException(sprintf('Thread type(%s) is error.', $thread['type']));
		}
		$thread['userId'] = $this->getCurrentUser()->id;
		// @todo filter it.
		$thread['title'] = empty($thread['title']) ? '' : $thread['title'];
		$thread['content'] = empty($thread['content']) ? '' : $thread['content'];
		$thread['createdTime'] = time();
		$thread['latestPostUserId'] = $thread['userId'];
		$thread['latestPostTime'] = $thread['createdTime'];
		return $this->getThreadDao()->addThread($thread);
	}

	public function updateThread($courseId, $threadId, $thread)
	{
		$thread = $this->getThread($courseId, $threadId);
		if (empty($thread)) {
			throw $this->createServiceException('话题不存在，更新失败！');
		}

		$fields = ArrayToolkit::parts($thread, array('title', 'content'));
		if (empty($fields)) {
			throw $this->createServiceException('参数缺失，更新失败。');
		}

	}

	public function deleteThread($courseId, $threadId)
	{
		$thread = $this->getThread($courseId, $threadId);
		if (empty($thread)) {
			throw $this->createServiceException(sprintf('话题(ID: %s)不存在。', $thread['id']));
		}

		$this->getThreadPostDao()->deletePostsByThreadId($thread['id']);
		$this->getThreadDao()->deleteThread($thread['id']);
	}

	public function stickThread($courseId, $threadId)
	{
		$thread = $this->getThread($courseId, $threadId);
		if (empty($thread)) {
			throw $this->createServiceException(sprintf('话题(ID: %s)不存在。', $thread['id']));
		}

		$this->getThreadDao()->updateThread($thread['id'], array('isStick' => 1));
	}

	public function unstickThread($courseId, $threadId)
	{
		$thread = $this->getThread($courseId, $threadId);
		if (empty($thread)) {
			throw $this->createServiceException(sprintf('话题(ID: %s)不存在。', $thread['id']));
		}

		$this->getThreadDao()->updateThread($thread['id'], array('isStick' => 0));
	}

	public function eliteThread($courseId, $threadId)
	{
		$thread = $this->getThread($courseId, $threadId);
		if (empty($thread)) {
			throw $this->createServiceException(sprintf('话题(ID: %s)不存在。', $thread['id']));
		}

		$this->getThreadDao()->updateThread($thread['id'], array('isElite' => 1));
	}

	public function uneliteThread($courseId, $threadId)
	{
		$thread = $this->getThread($courseId, $threadId);
		if (empty($thread)) {
			throw $this->createServiceException(sprintf('话题(ID: %s)不存在。', $thread['id']));
		}

		$this->getThreadDao()->updateThread($thread['id'], array('isElite' => 0));
	}

	public function hitThread($courseId, $threadId)
	{
		$this->getThreadDao()->waveThread($threadId, 'hitNum', +1);
	}

	public function findThreadPosts($courseId, $threadId, $sort = 'default', $start, $limit)
	{
		$thread = $this->getThread($courseId, $threadId);
		if (empty($thread)) {
			return array();
		}
		if ($sort == 'best') {
			$orderBy = array('score', 'DESC');
		} else {
			$orderBy = array('createdTime', 'ASC');
		}

		return $this->getThreadPostDao()->findPostsByThreadId($threadId, $orderBy, $start, $limit);
	}

	public function getThreadPostCount($courseId, $threadId)
	{
		return $this->getThreadPostDao()->getPostCountByThreadId($threadId);
	}

	public function postThread($post)
	{
		$requiredKeys = array('courseId', 'threadId', 'content');
		if (!ArrayToolkit::requireds($post, $requiredKeys)) {
			throw $this->createServiceException('参数缺失');
		}

		$thread = $this->getThread($post['courseId'], $post['threadId']);
		if (empty($thread)) {
			throw $this->createServiceException(sprintf('课程(ID: %s)话题(ID: %s)不存在。', $post['courseId'], $post['threadId']));
		}

		$post['userId'] = $this->getCurrentUser()->id;
		$post['createdTime'] = time();
		$post = $this->getThreadPostDao()->addPost($post);

		// 高并发的时候， 这样更新postNum是有问题的，这里暂时不考虑这个问题。
		$threadFields = array(
			'postNum' => $thread['postNum'] + 1,
			'latestPostUserId' => $post['userId'],
			'latestPostTime' => $post['createdTime'],
		);
		$this->getThreadDao()->updateThread($thread['id'], $threadFields);

		return $post;
	}

	public function deletePost($courseId, $id)
	{
		$post = $this->getThreadPostDao()->getPost($id);
		if (empty($post)) {
			throw $this->createServiceException(sprintf('帖子(#%s)不存在，删除失败。', $id));
		}

		if ($post['courseId'] != $courseId) {
			throw $this->createServiceException(sprintf('帖子#%s不属于课程#%s，删除失败。', $id, $courseId));
		}

		$this->getThreadPostDao()->deletePost($post['id']);
		$this->getThreadDao()->waveThread($post['threadId'], 'postNum', -1);
	}

	private function getThreadDao()
	{
		return $this->createDao('Course.ThreadDao');
	}

	private function getThreadPostDao()
	{
		return $this->createDao('Course.ThreadPostDao');
	}

	private function getCourseService()
	{
		return $this->createService('Course.CourseService');
	}

	private function getUserService()
    {
      	return $this->createService('User.UserService');
    }

}