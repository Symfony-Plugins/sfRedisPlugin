<?php

class sfRedisDoctrinePager extends sfPager
{
  public function init()
  {
    if (!$this->hasParameter('key'))
    {
      throw new sfConfigurationException(sprintf('%s needs "key" parameter', __CLASS__));
    }

    $this->resetIterator();

    $client = sfRedis::getClient($this->getParameter('connection', 'default'));

    if ($this->hasParameter('min') and $this->hasParameter('max'))
    {
      $nb = $client->zcount($this->getParameter('key'),
        $this->getParameter('min'), $this->getParameter('max')
      );
    }
    else
    {
      $nb = $client->zcard($this->getParameter('key'));
    }

    $this->setNbResults($nb);

    if (0 == $this->getPage() || 0 == $this->getMaxPerPage() || 0 == $this->getNbResults())
    {
      $this->setLastPage(0);
    }
    else
    {
      $this->setLastPage(ceil($this->getNbResults() / $this->getMaxPerPage()));
    }
  }

  public function getResults()
  {
    $count  = $this->getMaxPerPage();
    $offset = ($this->getPage() - 1) * $count;

    $client = sfRedis::getClient($this->getParameter('connection', 'default'));

    if ($this->hasParameter('min') and $this->hasParameter('max'))
    {
      return $client->zrangebyscore($this->getParameter('key'),
        $this->getParameter('min'), $this->getParameter('max'),
        array('limit' => array('offset' => $offset, 'count' => $count))
      );
    }
    else
    {
      return $client->zrange($this->getParameter('key'), $offset, $offset + $count);
    }
  }

  protected function retrieveObject($offset)
  {
    $id = sfRedis::getClient($this->getParameter('connection', 'default'))
      ->zrange($this->getParameter('key'), $offset, $offset)
    ;

    return $this->findObject($id);
  }

  public function current()
  {
    $id = parent::current();

    return $id ? $this->findObject($id) : false;
  }

  public function findObject($id)
  {
    $table = Doctrine_Core::getTable($this->getClass());

    if ($this->hasParameter('tableMethod'))
    {
      $methodName = $this->getParameter('tableMethod');
      return $table->$methodName()->andWhere('id = ?', $id)->fetchOne();
    }
    else
    {
      return $table->find($id);
    }

  }
}

