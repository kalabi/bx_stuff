<?php


namespace Custom;


class CustomElement extends CustomEntity
{


    /**
     * @param string $method
     *
     * @return array
     */
    public function getList($method = 'fetch')
    {

        if (empty($this->filter)) {
            $arFilter = [
                'IBLOCK_ID' => $this->ib,
                'ACTIVE'    => 'Y',
            ];
        }
        else {
            $arFilter = array_merge($this->filter, ['IBLOCK_ID' => $this->ib]);
        }


        $arSelect = [];

        foreach ($this->fields as $field) {
            $arSelect[] = $field;
        }

        foreach ($this->properties as $property) {
            $arSelect[] = 'PROPERTY_'.$property;
        }


        if (count($arSelect) === 0) {
            $arSelect = [
                'ID',
                'IBLOCK_ID',
                'NAME',
                'CODE'
            ];
        }

        if (count($this->order) === 0) {
            $arOrder = [
                'ID'   => 'DESC',
                'NAME' => 'ASC'
            ];
        }
        else {
            $arOrder = $this->order;
        }

        if ($this->debug) {
            $this->log[] = [
                'function'   => __FUNCTION__,
                'ib'         => $this->ib,
                'fields'     => $this->fields,
                'properties' => $this->properties,
                'filter'     => $arFilter,
                'select'     => $arSelect,
                'order'      => $this->order,
                'method'     => $method === 'fetch' ? 'fetch' : 'getNext'
            ];
        }

        $res = \CIBlockElement::GetList($arOrder, $arFilter, false, false, $arSelect);

        if ($method === 'fetch') {
            $items = $this->doFetch($res);
        }
        else {
            $items = $this->doGetNext($res);
        }

        $this->clear();

        return $items;
    }


    /**
     * @param $res \CDBResult
     *
     * @return array
     */
    private function doFetch($res)
    {
        $items = [];

        while ($ob = $res->Fetch()) {

            if ($this->callback) {
                $ob = $this->callback($ob);
            }

            $items[] = $ob;
        }

        return $items;
    }


    /**
     * @param $res \CDBResult
     *
     * @return array
     */
    private function doGetNext($res)
    {
        $items = [];

        while ($ob = $res->GetNext()) {
            $items[] = $ob;
        }

        return $items;
    }


    /**
     * @return bool|int|string
     */
    public function add()
    {
        $el = new \CIBlockElement;


        if (count($this->fields) === 0) {
            return false;
        }
        else {

            if (count($this->properties) > 0) {
                $arFields = array_merge($this->fields,
                                        [
                                            'IBLOCK_ID'       => $this->ib,
                                            'PROPERTY_VALUES' => $this->properties
                                        ]);
            }
            else {
                $arFields = array_merge($this->fields, ['IBLOCK_ID' => $this->ib]);
            }
        }

        if ($this->debug) {
            $this->log[] = [
                'function'   => __FUNCTION__,
                'ib'         => $this->ib,
                'fields'     => $this->fields,
                'properties' => $this->properties,
                'filter'     => $this->filter,
                'array'      => $arFields
            ];
        }

        if ($PRODUCT_ID = $el->Add($arFields)) {
            $this->clear();

            return (int)$PRODUCT_ID;
        }
        else {
            return $el->LAST_ERROR;
        }
    }

    /**
     * @param $id
     */
    public function delete($id)
    {
        global $DB;

        $DB->StartTransaction();
        if (!\CIBlockElement::Delete($id)) {
            $DB->Rollback();
        }
        else
            $DB->Commit();
    }

    /**
     * @param $id
     *
     * @return bool|int|string
     */
    public function update($id)
    {
        $el = new \CIBlockElement;


        if (count($this->fields) === 0) {
            return false;
        }
        else {
            $arFields = $this->fields;
        }

        if ($this->debug) {
            $this->log[] = [
                'function'   => __FUNCTION__,
                'ib'         => $this->ib,
                'fields'     => $this->fields,
                'properties' => $this->properties,
                'filter'     => $this->filter,
                'array'      => $arFields
            ];
        }

        if ($PRODUCT_ID = $el->Update($id, $arFields)) {
            $this->clear();

            return (int)$PRODUCT_ID;
        }
        else {
            if ($this->debug) {
                $this->log[] = [
                    __FUNCTION__,
                    $el->LAST_ERROR
                ];
            }

            return $el->LAST_ERROR;
        }
    }

    /**
     * @param $id
     *
     * @return bool
     */
    public function updateProperty($id)
    {
        if (count($this->properties) === 0) {
            if ($this->debug) {
                $this->log[] = [
                    __FUNCTION__,
                    'empty properties',
                ];
            }

            return false;
        }

        \CIBlockElement::SetPropertyValuesEx($id, $this->ib, $this->properties);

        $this->clear();

        return true;
    }

    /**
     * @param $id
     *
     * @return bool|mixed
     */
    public function get($id)
    {

        if (!$id) {
            if ($this->debug) {
                $this->log[] = [
                    __FUNCTION__,
                    'empty id'
                ];
            }
        }

        $res = \CIBlockElement::GetByID($id);
        if ($ar_res = $res->GetNext()) return $ar_res;

        return false;
    }
}