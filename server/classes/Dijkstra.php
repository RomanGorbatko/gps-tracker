<?php 

/**
 * Класс описывающий структуру вершины
 */
class vertex
{
    public $key         = null;
    public $visited     = 0;
    public $distance    = 1000000;  // infinite
    public $parent      = null;
    public $path        = null;
 
    public function __construct($key) 
    {
        $this->key = $key;
    }
}
 
/**
 * Класс переопределяет метод compare стандартного класса
 * для реализации приоритетной очереди.
 */
class PriorityQueue extends SplPriorityQueue
{
    public function compare($a, $b)
    {
        if ($a === $b) return 0;
        return $a > $b ? -1 : 1;
    }
}

/**
 * Алгоритм Дейкстры
 * Описание: http://www.stoimen.com/blog/2012/10/15/computer-algorithms-dijkstra-shortest-path-in-a-graph/
 */
class calcShortestPaths
{
    
    public function __construct(vertex $start, $adjLists)
    {
        $q = new PriorityQueue();
 
        // push the starting vertex into the queue
        $q->insert($start, 0);
        $q->rewind();
     
        // mark the distance to it 0
        $start->distance = 0;
     
        // the path to the starting vertex
        $start->path = array($start->key);

        while ($q->valid()) {
            $t = $q->extract();
            $t->visited = 1;
            $l = $adjLists[$t->key];

            while ($l->valid()) {
                $item = $l->current();

                if (!$item['vertex']->visited) {
                    if ($item['vertex']->distance > $t->distance + $item['distance']) {
                        $item['vertex']->distance = $t->distance + $item['distance'];
                        $item['vertex']->parent = $t;
                    }
     
                    $item['vertex']->path = array_merge($t->path, array($item['vertex']->key));
     
                    $q->insert($item["vertex"], $item["vertex"]->distance);
                }
                $l->next();
            }
            $q->recoverFromCorruption();
            $q->rewind();
        }
    }
}

?>