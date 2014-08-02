<?php
/**
 * @package   ImpressPages
 */

namespace Ip\Internal\Grid\Model;


class Table extends \Ip\Internal\Grid\Model
{

    /**
     * @var Config
     */
    protected $config = null;
    protected $subgridConfig = null;
    protected $request = null;

    public function __construct($config, $request)
    {
        $this->request = $request;
        $this->config = new Config($config);


        $hash = ipRequest()->getRequest('hash', '');


        $this->statusVariables = Status::parse($hash);

        $this->subgridConfig = $this->config->subgridConfig($this->statusVariables);


        $this->actions = new Actions($this->subgridConfig);
    }

    public function handleMethod()
    {
        $request = $this->request->getRequest();

        if (empty($request['method'])) {
            throw new \Ip\Exception('Missing request data');
        }
        $method = $request['method'];

        if (in_array($method, array('update', 'create', 'delete', 'move'))) {
            $this->request->mustBePost();
        }

        if (in_array($method, array('update', 'create'))) {
            $data = $this->request->getPost();
            $params = $data;
        } elseif (in_array($method, array('search'))) {
            $data = $this->request->getQuery();
            $params = $data;
        } else {
            $data = $this->request->getRequest();
            $params = empty($data['params']) ? array() : $data['params'];
        }




        if ($this->subgridConfig->preventAction()) {
            $preventReason = call_user_func($this->subgridConfig->preventAction(), $method, $params, $this->statusVariables);
            if ($preventReason) {
                if (is_array($preventReason)) {
                    return $preventReason;
                } else {
                    return array(
                        Commands::showMessage($preventReason)
                    );
                }
            }
        }

        unset($params['method']);
        unset($params['aa']);


        switch ($method) {
            case 'init':
                return $this->init();
                break;
            case 'page':
                return $this->page($params);
                break;
            case 'delete':
                return $this->delete($params);
                break;
            case 'updateForm':
                $updateForm = $this->updateForm($params);
                $view = ipView('../view/updateForm.php', array('updateForm' => $updateForm))->render();
                return $view;
                break;
            case 'update':
                return $this->update($params);
                break;
            case 'move':
                return $this->move($params);
                break;
            case 'create':
                return $this->create($params);
                break;
            case 'search':
                return $this->search($params);
                break;
            case 'subgrid':
                return $this->subgrid($params);
                break;
        }
        return null;
    }




    protected function init()
    {

        $display = $this->getDisplay();
        $commands = array();
        $html = $display->fullHtml($this->statusVariables);
        $commands[] = Commands::setHtml($html);
        return $commands;
    }

    protected function page($params)
    {

        $statusVariables = $this->statusVariables;
        $pageVariableName = $this->subgridConfig->pageVariableName();
        if (empty($params['page'])) {
            throw new \Ip\Exception('Missing parameters');
        }

        $statusVariables[$pageVariableName] = $params['page'];
        $commands = array();
        $commands[] = Commands::setHash(Status::build($statusVariables));
        return $commands;
    }

    protected function delete($params)
    {
        if (empty($params['id'])) {
            throw new \Ip\Exception('Missing parameters');
        }

        if ($this->subgridConfig->beforeDelete()) {
            call_user_func($this->subgridConfig->beforeDelete(), $params['id']);
        }

        try {
            $actions = new Actions($this->subgridConfig);
            $actions->delete($params['id']);
            $display = $this->getDisplay();
            $html = $display->fullHtml($this->statusVariables);
            $commands[] = Commands::setHtml($html);
            return $commands;
        } catch (\Exception $e) {
            $commands[] = Commands::showMessage($e->getMessage());
        }

        if ($this->subgridConfig->afterDelete()) {
            call_user_func($this->subgridConfig->afterDelete(), $params['id']);
        }
        return $commands;

    }

    protected function updateForm($params)
    {
        $display = $this->getDisplay();
        $updateForm = $display->updateForm($params['id']);
        return $updateForm;
    }

    protected function update($data)
    {
        if (empty($data[$this->subgridConfig->idField()])) {
            throw new \Ip\Exception('Missing parameters');
        }
        $recordId = $data[$this->subgridConfig->idField()];
        $display = $this->getDisplay();
        $updateForm = $display->updateForm($recordId);


        $errors = $updateForm->validate($data);

        if ($errors) {
            $data = array(
                'error' => 1,
                'errors' => $errors
            );
        } else {
            $newData = $updateForm->filterValues($data);

            if ($this->subgridConfig->beforeUpdate()) {
                call_user_func($this->subgridConfig->beforeUpdate(), $recordId, $newData);
            }

            $actions = new Actions($this->subgridConfig);
            $actions->update($recordId, $newData);

            if ($this->subgridConfig->afterUpdate()) {
                call_user_func($this->subgridConfig->afterUpdate(), $recordId, $newData);
            }

            $display = $this->getDisplay();
            $html = $display->fullHtml();
            $commands[] = Commands::setHtml($html);

            $data = array(
                'error' => 0,
                'commands' => $commands
            );
        }

        return $data;
    }

    protected function create($data)
    {
        $display = $this->getDisplay();
        $createForm = $display->createForm();


        $errors = $createForm->validate($data);

        if ($errors) {
            $data = array(
                'error' => 1,
                'errors' => $errors
            );
        } else {
            $newData = $createForm->filterValues($data);



            if ($this->subgridConfig->beforeCreate()) {
                call_user_func($this->subgridConfig->beforeCreate(), $newData);
            }

            $actions = new Actions($this->subgridConfig);
            $recordId = $actions->create($newData);

            if ($this->subgridConfig->afterCreate()) {
                call_user_func($this->subgridConfig->afterCreate(), $recordId, $newData);
            }

            $display = $this->getDisplay();
            $html = $display->fullHtml();
            $commands[] = Commands::setHtml($html);

            $data = array(
                'error' => 0,
                'commands' => $commands
            );
        }

        return $data;
    }

    protected function move($params)
    {
        if (empty($params['id']) || empty($params['targetId']) || empty($params['beforeOrAfter'])) {
            throw new \Ip\Exception('Missing parameters');
        }

        if ($this->subgridConfig->beforeMove()) {
            call_user_func($this->subgridConfig->beforeMove(), $params['id']);
        }

        $id = $params['id'];
        $targetId = $params['targetId'];
        $beforeOrAfter = $params['beforeOrAfter'];

        $actions = new Actions($this->subgridConfig);
        $actions->move($id, $targetId, $beforeOrAfter);
        $display = $this->getDisplay();
        $html = $display->fullHtml();
        $commands[] = Commands::setHtml($html);

        if ($this->subgridConfig->afterMove()) {
            call_user_func($this->subgridConfig->afterMove(), $params['id']);
        }
        return $commands;
    }

    protected function search($data)
    {
        $statusVariables = $this->statusVariables;
        $display = $this->getDisplay();
        $searchForm = $display->searchForm(array());


        $errors = $searchForm->validate($data);

        if ($errors) {
            $data = array(
                'error' => 1,
                'errors' => $errors
            );
        } else {
            $newData = $searchForm->filterValues($data);


            foreach ($newData as $key => $value) {
                if (in_array($key, array('antispam', 'securityToken')) ) {
                    continue;
                }
                if(empty($value)) {
                    unset($statusVariables['s_' . $key]);
                    continue;
                }

                $statusVariables['s_' . $key] = $value;
            }

            $commands[] = Commands::setHash(Status::build($statusVariables));

            $data = array(
                'error' => 0,
                'commands' => $commands
            );
        }

        return $data;
    }


    protected function subgrid($params)
    {
        if (empty($params['gridId'])) {
            throw new \Ip\Exception('girdId GET variable missing');
        }
        if (empty($params['gridParentId'])) {
            throw new \Ip\Exception('girdParentId GET variable missing');
        }

        $newStatusVariables = array();

        $depth = Status::depth($this->statusVariables);

        for($i=1; $i<$depth; $i++) {
            $newStatusVariables['gridId' . $i] = $this->statusVariables['gridId' . $i];
            $newStatusVariables['gridParentId' . $i] = $this->statusVariables['gridParentId' . $i];
        }


        $newStatusVariables['gridId' . $depth] = $params['gridId'];
        $newStatusVariables['gridParentId' . $depth] = $params['gridParentId'];

        $commands[] = Commands::setHash(Status::build($newStatusVariables));

        return $commands;
    }

    protected function getDisplay()
    {
        return new Display($this->config, $this->subgridConfig, $this->statusVariables);
    }

}
