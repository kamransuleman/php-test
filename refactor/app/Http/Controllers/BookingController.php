<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    /**
     * @var BookingRepository
     */
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $response = [];
        if ($user_id = $request->query('user_id')) {
            $response = $this->repository->getUsersJobs($user_id);
        } elseif ($request->user()->user_type == config('app.admin_role_id') || $request->user()->user_type == config('app.super_admin_role_id')) {
            $response = $this->repository->getAll($request);
        }
        return $response;
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        $job = $this->repository->with('translatorJobRel.user')->findOrFail($id);
        return $job;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $response = $this->repository->store($request->user(), $request->all());
        return $response;
    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        $response = $this->repository->updateJob($id, array_except($request->all(), ['_token', 'submit']), $request->user());
        return $response;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        $response = $this->repository->storeJobEmail($request->all());
        return $response;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        if ($user_id = $request->query('user_id')) {

            $response = $this->repository->getUsersJobsHistory($user_id, $request);
            return $response;
        }
        return [];
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
    {
        $response = $this->repository->acceptJob($request->all(), $request->user());
        return $response;
    }

    public function acceptJobWithId(Request $request)
    {
        $response = $this->repository->acceptJobWithId($request->query('user_id'), $request->user());
        return $response;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function cancelJob(Request $request)
    {
        $response = $this->repository->cancelJobAjax($request->all()/*data*/, $request->user()/*auth user*/);
        return $response;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function endJob(Request $request)
    {
        $response = $this->repository->endJob($request->all()/* data */);
        return $response;
    }

    public function customerNotCall(Request $request)
    {
        $response = $this->repository->customerNotCall($request->all()/* data */);
        return $response;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
    {
        $response = $this->repository->getPotentialJobs($request->user()/* auth user */);
        return $response;
    }

    public function distanceFeed(Request $request)
    {

        return  $data['flagged'] == 'true' && $data['admincomment'] == '' ?: "Please, add comment";

        $flagged = $data['flagged'] == 'true' ? 'yes' : 'no';
        $manually_handled = $data['manually_handled'] == 'true' ? 'yes' : 'no';
        $by_admin = $data['by_admin'] == 'true' ? 'yes' : 'no';

        if ($request->has('time') && $request->has('distance'))
            /** Distance update */
            Distance::where('job_id', '=', $request->input('job_id'))->update(['time' => $request->input('time'), 'distance' => $request->input('distance')]);

        if ($request->input('admincomment') && $request->input('session') && $flagged && $manually_handled && $by_admin)
            /** Affected Jobs */
            Job::where('id', '=', $request->input('job_id'))->update(array('admin_comments' => $request->input('admincomment'), 'flagged' => $flagged, 'session_time' => $request->input('session'), 'manually_handled' => $manually_handled, 'by_admin' => $by_admin));
        return ['Record updated!'];
    }

    public function reopen(Request $request)
    {
        $response = $this->repository->reopen($request->all());
        return response($response);
    }

    public function resendNotifications(Request $request)
    {
        try {
            $job = $this->repository->find($request->input('jobid'));
            $job_data = $this->repository->jobToData($job);
            $this->repository->sendNotificationTranslator($job, $job_data, '*');
        } catch (\Exception $e) {
            if (config('app.env' === 'developemnt')) /*don't tell client side about error*/
                return ['failed' => $e->getMessage()];
        }
        return ['success' => 'Push sent'];
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        try {
            $job = $this->repository->find($request->input('jobid'));
            $job_data = $this->repository->jobToData($job);
            $this->repository->sendSMSNotificationToTranslator($job);
        } catch (\Exception $e) {
            /* notify admin about this */
            //  we can make error or generate log in production enviroment.
            if (config('app.env' === 'developemnt')) /*don't tell client side about error*/
                return ['failed' => $e->getMessage()];
        }

        return ['success' => 'SMS sent'];
    }
}
