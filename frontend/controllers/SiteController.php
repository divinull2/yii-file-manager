<?php
namespace frontend\controllers;

use Yii;
use yii\base\InvalidParamException;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\helpers\Url;
use common\models\LoginForm;
use frontend\models\PasswordResetRequestForm;
use frontend\models\ResetPasswordForm;
use frontend\models\SignupForm;
use frontend\models\ContactForm;
use app\models\CreateTaskForm;
use app\models\CreateProjectForm;
use app\models\UpdateTaskForm;
use app\models\EditTaskForm;
use app\models\EditProjectForm;
use app\models\Task;
use app\models\Project;

/**
 * Site controller
 */
class SiteController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout', 'signup'],
                'rules' => [
                    [
                        'actions' => ['signup'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return mixed
     */
    public function actionIndex()
    {

        $userStatus = 0;
        if(!Yii::$app->user->isGuest){
            $userStatus = Yii::$app->user->identity->permission;
        }
        return $this->render('index', ['userStatus' => $userStatus]);
    }

    /**
     * Logs in a user.
     *
     * @return mixed
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        } else {
            return $this->render('login', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Logs out the current user.
     *
     * @return mixed
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /**
     * Displays contact page.
     *
     * @return mixed
     */
    public function actionContact()
    {
        $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->sendEmail(Yii::$app->params['adminEmail'])) {
                Yii::$app->session->setFlash('success', 'Thank you for contacting us. We will respond to you as soon as possible.');
            } else {
                Yii::$app->session->setFlash('error', 'There was an error sending your message.');
            }

            return $this->refresh();
        } else {
            return $this->render('contact', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Displays about page.
     *
     * @return mixed
     */
    public function actionAbout()
    {
        return $this->render('about');
    }

    /**
     * Signs user up.
     *
     * @return mixed
     */
    public function actionSignup()
    {
        $model = new SignupForm();
        if ($model->load(Yii::$app->request->post())) {
            if ($user = $model->signup()) {
                if (Yii::$app->getUser()->login($user)) {
                    return $this->goHome();
                }
            }
        }

        return $this->render('signup', [
            'model' => $model,
        ]);
    }

    /**
     * Requests password reset.
     *
     * @return mixed
     */
    public function actionRequestPasswordReset()
    {
        $model = new PasswordResetRequestForm();
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->sendEmail()) {
                Yii::$app->session->setFlash('success', 'Check your email for further instructions.');

                return $this->goHome();
            } else {
                Yii::$app->session->setFlash('error', 'Sorry, we are unable to reset password for the provided email address.');
            }
        }

        return $this->render('requestPasswordResetToken', [
            'model' => $model,
        ]);
    }

    /**
     * Resets password.
     *
     * @param string $token
     * @return mixed
     * @throws BadRequestHttpException
     */
    public function actionResetPassword($token)
    {
        try {
            $model = new ResetPasswordForm($token);
        } catch (InvalidParamException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        if ($model->load(Yii::$app->request->post()) && $model->validate() && $model->resetPassword()) {
            Yii::$app->session->setFlash('success', 'New password saved.');

            return $this->goHome();
        }

        return $this->render('resetPassword', [
            'model' => $model,
        ]);
    }

    // This is where my code starts
    /*
     * The why I thought it is that a project can have more tasks and
     * we can have more projects.
     * */
    // This is where I get project information for the project list
    public function actionProjects()
    {
        $userStatus = 0;
        if(!Yii::$app->user->isGuest){
            $userStatus = Yii::$app->user->identity->permission;
        }
        $editor = 0;
        if($userStatus == 10 || $userStatus == 20){
            $editor = 1;
        }

        $query = new \yii\db\Query;
        $query->select('project.*, user.username')->from('project')->leftJoin('user', 'project.createdby = user.id');
        $command = $query->createCommand();
        $projects = $command->queryAll();

        return $this->render('projects',
            [
                'projects' => $projects,
                'editor' => $editor,
            ]);
    }

    // This is where I get task information for the task list
    public function actionProject()
    {
        $userStatus = 0;
        if(!Yii::$app->user->isGuest){
            $userStatus = Yii::$app->user->identity->permission;
        }
        $editor = 0;
        $taskMaker = 0;
        if($userStatus == 10 || $userStatus == 20){
            $editor = 1;
        }
        if($userStatus == 10 || $userStatus == 20 || $userStatus == 50){
            $taskMaker = 1;
        }
        $request = Yii::$app->request;
        $get = $request->get();
        $query = new \yii\db\Query;
        if($userStatus == 30){
            $query->select('task.*,
                task_status.name as tsname,
                priority.name as pname,
                user.username as dvname,
                user2.username as crname')
                ->from('task')
                ->leftJoin('task_status', 'task.status = task_status.id')
                ->leftJoin('priority', 'task.priority = priority.id')
                ->leftJoin('user', 'task.developerid = user.id')
                ->leftJoin('user as user2', 'task.createdby = user2.id')
                ->where('projectid = '  . $get['id'])
                ->andWhere('user.id = ' . Yii::$app->user->identity->id);
        }else{
            $query->select('task.*,
                task_status.name as tsname,
                priority.name as pname,
                user.username as dvname,
                user2.username as crname')
                ->from('task')
                ->leftJoin('task_status', 'task.status = task_status.id')
                ->leftJoin('priority', 'task.priority = priority.id')
                ->leftJoin('user', 'task.developerid = user.id')
                ->leftJoin('user as user2', 'task.createdby = user2.id')
                ->where('projectid = '  . $get['id']);
        }
        $command = $query->createCommand();
        $resp = $command->queryAll();

        return $this->render('project',
            [
                'projectId' => $get['id'],
                'tasks' => $resp,
                'editor' => $editor,
                'taskMaker' => $taskMaker,
            ]);
    }

    // This is where I get task information for the task page
    public function actionTask()
    {
        $userStatus = 0;
        if(!Yii::$app->user->isGuest){
            $userStatus = Yii::$app->user->identity->permission;
        }
        $request = Yii::$app->request;
        $get = $request->get();
        if(isset($get['id'])){
            $editor = 0;
            if($userStatus == 10 || $userStatus == 20){
                $editor = 1;
            }
            $intelligenceMember = 0;
            if($userStatus == 50){
                $intelligenceMember = 1;
            }
            $query = new \yii\db\Query;
            $query->select('task.*,
                project.name as projectname,
                task_status.name as task_status_name,
                priority.name as pr_name,
                user.username as created_task,
                user2.username as assigned_to')
                ->from('task')
                ->leftJoin('project', 'task.projectid=project.id')
                ->leftJoin('task_status', 'task.status = task_status.id')
                ->leftJoin('priority','task.priority = priority.id')
                ->leftJoin('user', 'task.createdby = user.id')
                ->leftJoin('user as user2', 'task.developerid = user2.id')
                ->where('task.id = ' . $get['id']);
            $command = $query->createCommand();
            $resp = $command->queryAll();
            $task =  $resp[0];
            return $this->render('task', [
                'task' => $task,
                'editor' => $editor,
                'intelligenceMember' => $intelligenceMember,
            ]);
        }

        return $this->render('index');
    }

    public function actionCreateTask()
    {
        $model = new CreateTaskForm();

        if($model->load(Yii::$app->request->post()) && $model->validate()){

            $formPost = Yii::$app->request->post()['CreateTaskForm'];
            $task = new Task();
            $task->name = $formPost['name'];
            $task->projectid = $formPost['projectid'];
            $task->description = $formPost['description'];
            $task->createdby = $formPost['createdby'];
            $task->developerid = $formPost['developerid'];
            $task->priority = $formPost['priority'];
            $time = Yii::$app->formatter->asTime($formPost['estimated'] * 60, 'php:H:i:s');
            $task->estimated = $time;
            $task->createdat = $formPost['createdat'];
            $task->due = $formPost['due'];
            $task->save();
            return $this->render('create-task-confirm', [
                'name' => $formPost['name']
            ]);
        }

        $projects = getQueryList('project', 'id, name', 'id', 'name');
        $users = getQueryList('user', 'id, username', 'id', 'username');
        $priorities = getQueryList('priority', '*', 'id', 'name');
        $request = Yii::$app->request;
        $get = $request->get();
        if(isset($get['id'])){
            return $this->render('create-task', [
                'model' => $model,
                'projects' => $projects,
                'users' => $users,
                'priorities' => $priorities,
                'projectId' => $get['id'],
            ]);
        }
        return $this->render('create-task', [
            'model' => $model,
            'projects' => $projects,
            'users' => $users,
            'priorities' => $priorities,
        ]);
    }

    public function actionUpdateTask(){
        $model = new UpdateTaskForm();
        $request = Yii::$app->request;
        $get = $request->get();

        if($model->load(Yii::$app->request->post())  && $model->validate()){
            $formPost = Yii::$app->request->post()['UpdateTaskForm'];
            $task = Task::findOne($get['id']);
            $task->name = $formPost['name'];
            $task->description = $formPost['description'];
            $task->update();
            return $this->render('update-task-confirm', [
                'name' => $formPost['name'],
            ]);
        }

        $task = Task::findOne($get['id']);
        return $this->render('update-task', [
            'model' => $model,
            'task' => $task,
        ]);
    }

    public function actionEditTask(){
        $model = new EditTaskForm();
        $request = Yii::$app->request;
        $get = $request->get();

        if($model->load(Yii::$app->request->post())  && $model->validate()){
            $formPost = Yii::$app->request->post()['EditTaskForm'];
            $task = Task::findOne($get['id']);
            $task->name = $formPost['name'];
            $task->description = $formPost['description'];
            $task->developerid = $formPost['developerid'];
            $task->status = $formPost['status'];
            $task->priority = $formPost['priority'];
            $task->estimated = $formPost['estimated'];
            $task->elapsed = $formPost['elapsed'];
            $task->due = $formPost['due'];
            $task->update();

            return $this->render('edit-task-confirm', [
                'name' => $formPost['name'],
            ]);
        }

        $task = Task::findOne($get['id']);

        $users = getQueryList('user', 'id, username', 'id', 'username');
        $status = getQueryList('task_status', '*', 'id', 'name');
        $priorities = getQueryList('priority', '*', 'id', 'name');

        return $this->render('edit-task',[
            'model' => $model,
            'task' => $task,
            'listStatus' => $status,
            'priorities' => $priorities,
            'users' => $users,
        ]);
    }

    public function actionCreateProject(){

        $model = new CreateProjectForm();

        if($model->load(Yii::$app->request->post()) && $model->validate()){

            $formPost = Yii::$app->request->post()['CreateProjectForm'];
            $project = new Project();
            $project->name = $formPost['name'];
            $project->description = $formPost['description'];
            $project->abbreviation = $formPost['abbreviation'];
            $project->createdby = $formPost['createdby'];
            $project->createdat = $formPost['createdat'];
            $project->save();

            return $this->render('create-project-confirm', [
                'name' => $formPost['name'],
            ]);

        }

        $users = getQueryList('user', 'id, username', 'id', 'username');

        return $this->render('create-project', [
            'model' => $model,
            'users' => $users,
        ]);
    }

    public function actionEditProject(){
        $model = new EditProjectForm();
        $request = Yii::$app->request;
        $get = $request->get();
        $project = Project::findOne($get['id']);

        if($model->load(Yii::$app->request->post()) && $model->validate()){
            $formPost = Yii::$app->request->post()['EditProjectForm'];
            $project->name = $formPost['name'];
            $project->description = $formPost['description'];
            $project->abbreviation = $formPost['abbreviation'];
            $project->update();

            return $this->render('edit-project-confirm', [
                'model' => $model,
                'name' => $formPost['name'],
            ]);
        }

        return $this->render('edit-project', [
            'model' => $model,
            'project' => $project,
        ]);
    }
}

function getQueryList($tableName, $select, $index, $value){
    $query = new \yii\db\Query;
    $query->select($select)->from($tableName);
    $command = $query->createCommand();
    $itemList = $command->queryAll();
    $items = [];
    foreach ($itemList as $item){
        $items[$item[$index]] = $item[$value];
    }
    return $items;
}