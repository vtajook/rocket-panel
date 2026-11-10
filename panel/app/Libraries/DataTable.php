<?php
/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

namespace App\Libraries;

if (!defined('PATH')) die();

class DataTable
{


    private $query = null;
    private $postData = null;


    public function __construct($query, $pdata = [])
    {
        $this->query = $query;
        $this->postData = $pdata;
    }


    public function query()
    {
        $this->getFiltering();
        $this->getOrdering();
        $this->getPaging();
        return $this->query->get();
    }

    private $filtered = false;
    private $starTable = null;

    private function resolve($map, $name)
    {
        if (isset($map[$name])) {
            return $map[$name];
        }
        if ($this->starTable && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            return $this->starTable . '.' . $name;
        }
        return null;
    }

    /** the plain query builder, whether we were given an Eloquent or a base builder */
    private function base()
    {
        return method_exists($this->query, 'getQuery') ? $this->query->getQuery() : $this->query;
    }

    /**
     * Columns the browser may sort / search by: only what the query selects.
     * "users.username" is offered as "username", "admins.fullname as admin_name"
     * as "admin_name". Anything else sent by the browser is ignored, so request
     * data never becomes part of the SQL text.
     */
    private function columnMap()
    {
        $map = [];
        $columns = $this->base()->columns;
        if (is_array($columns)) {
            foreach ($columns as $column) {
                if (!is_string($column)) {
                    continue;
                }
                if (preg_match('/^([A-Za-z0-9_.]+)\s+as\s+([A-Za-z0-9_]+)$/i', trim($column), $m)) {
                    $map[$m[2]] = $m[1];
                } elseif (preg_match('/^([A-Za-z0-9_]+\.)?([A-Za-z0-9_]+)$/', trim($column), $m)) {
                    $map[$m[2]] = trim($column);
                } elseif (preg_match('/^([A-Za-z0-9_]+)\.\*$/', trim($column), $m)) {
                    // "table.*": any plain column name of that table is acceptable
                    $this->starTable = $m[1];
                }
            }
        }
        return $map;
    }

    private function countRows()
    {
        $counter = clone $this->base();
        $counter->orders = null;
        $counter->limit  = null;
        $counter->offset = null;
        return $counter->count();
    }

    public function make($data)
    {
        // totals are taken without paging; "filtered" includes the search box
        $iFilteredTotal = $this->countRows();
        $iTotal         = $iFilteredTotal;

        $sOutput = array(
            'draw'              => intval(isset($this->postData['draw']) ? $this->postData['draw'] : 0),
            'recordsTotal'      => $iTotal,
            'recordsFiltered'   => $iFilteredTotal,
            'data'              => $data
        );
        return  $sOutput;
    }

    private function getPaging()
    {
        $iStart  = isset($this->postData['start']) ? intval($this->postData['start']) : 0;
        $iLength = isset($this->postData['length']) ? intval($this->postData['length']) : 25;
        if ($iLength > 0) {
            $this->query->offset(max(0, $iStart))->limit(min($iLength, 1000));
        }
    }

    private function getOrdering()
    {
        $columns = isset($this->postData['columns']) && is_array($this->postData['columns']) ? $this->postData['columns'] : [];
        $map     = $this->columnMap();
        if (!empty($this->postData['order']) && is_array($this->postData['order'])) {
            foreach ($this->postData['order'] as $key) {
                $index = isset($key['column']) ? intval($key['column']) : -1;
                $name  = isset($columns[$index]['data']) ? (string) $columns[$index]['data'] : '';
                $field = $this->resolve($map, $name);
                if ($field) {
                    $dir = (isset($key['dir']) && strtolower($key['dir']) === 'desc') ? 'desc' : 'asc';
                    $this->query->orderBy($field, $dir);
                }
            }
        }
    }

    private function getFiltering()
    {
        if ($this->filtered) {
            return;
        }
        $this->filtered = true;

        $columns = isset($this->postData['columns']) && is_array($this->postData['columns']) ? $this->postData['columns'] : [];
        $sSearch = isset($this->postData['search']['value']) ? trim((string) $this->postData['search']['value']) : '';
        if ($sSearch === '') {
            return;
        }

        $map    = $this->columnMap();
        $fields = [];
        foreach ($columns as $column) {
            $name = isset($column['data']) ? (string) $column['data'] : '';
            $field = $this->resolve($map, $name);
            if (isset($column['searchable']) && $column['searchable'] == 'true' && $field) {
                $fields[] = $field;
            }
        }
        if (!$fields) {
            return;
        }

        $this->query->where(function ($q) use ($fields, $sSearch) {
            foreach ($fields as $field) {
                $q->orWhere($field, 'LIKE', '%' . $sSearch . '%');
            }
        });
    }
}
