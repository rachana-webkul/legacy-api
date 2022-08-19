<?php

namespace Webkul\API\DataGrids;

use Webkul\Ui\DataGrid\DataGrid;
use Webkul\Core\Models\Locale;
use Webkul\Core\Models\Channel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PushNotificationDataGrid extends DataGrid
{
    /**
     * Default sort order of datagrid.
     *
     * @var string
     */
    protected $sortOrder = 'desc';

    /**
     * Set index columns, ex: id.
     *
     * @var string
     */
    protected $index = 'notification_id';

    /**
     * If paginated then value of pagination.
     *
     * @var int
     */
    protected $itemsPerPage = 10;

    /**
     * Locale.
     *
     * @var string
     */
    protected $locale = 'all';

    /**
     * Channel.
     *
     * @var string
     */
    protected $channel = 'all';

    /**
     * Contains the keys for which extra filters to show.
     *
     * @var string[]
     */
    protected $extraFilters = [
        'channels',
        'locales',
    ];

    /**
     * Create datagrid instance.
     *
     * @return void
     */
    public function __construct()
    {
        /* locale */
        $this->locale = core()->getRequestedLocaleCode();

        /* channel */
        $this->channel = core()->getRequestedChannelCode();

        /* parent constructor */
        parent::__construct();

        /* finding channel code */
        if ($this->channel !== 'all') {
            $this->channel = Channel::query()->where('code', $this->channel)->first();
            $this->channel = $this->channel ? $this->channel->code : 'all';
        }
    }

    /**
     * Prepare query builder.
     *
     * @return void
     */
    public function prepareQueryBuilder()
    {
        if ($this->channel === 'all') {
            $whereInChannels = Channel::query()->pluck('code')->toArray();
        } else {
            $whereInChannels = [$this->channel];
        }

        if ($this->locale === 'all') {
            $whereInLocales = Locale::query()->pluck('code')->toArray();
        } else {
            $whereInLocales = [$this->locale];
        }

        $queryBuilder = DB::table('push_notification_translations as pn_trans')
                            ->leftJoin('push_notifications as pn', 'pn_trans.push_notification_id', '=', 'pn.id')
                            ->leftJoin('channels as ch', 'pn_trans.channel', '=', 'ch.code')
                            ->leftJoin('channel_translations as ch_t', 'ch.id', '=', 'ch_t.channel_id')
                            ->addSelect(
                                'pn_trans.push_notification_id as notification_id',
                                'pn.image',
                                'pn_trans.title',
                                'pn_trans.content',
                                'pn_trans.channel',
                                'pn_trans.locale',
                                'pn.type',
                                'pn.product_category_id',
                                'pn.status',
                                'pn.created_at',
                                'pn.updated_at',
                                'ch_t.name as channel_name'
                            );
        
            $queryBuilder->groupBy('pn_trans.push_notification_id', 'pn_trans.channel', 'pn_trans.locale');

            $queryBuilder->whereIn('pn_trans.locale', $whereInLocales);
            $queryBuilder->whereIn('pn_trans.channel', $whereInChannels);

        $this->addFilter('notification_id', 'pn_trans.push_notification_id');
        $this->addFilter('title', 'pn_trans.title');
        $this->addFilter('content', 'pn_trans.content');
        $this->addFilter('channel_name', 'ch_t.name');
        $this->addFilter('status', 'pn.status');
        $this->addFilter('type', 'pn.type');

        $this->setQueryBuilder($queryBuilder);
    }

    /**
     * Add columns.
     *
     * @return void
     */
    public function addColumns()
    {
        $this->addColumn([
            'index'         => 'notification_id',
            'label'         => trans('api::app.notification.id'),
            'type'          => 'number',
            'searchable'    => true,
            'sortable'      => true,
            'filterable'    => true,
        ]);

        $this->addColumn([
            'index'         => 'image',
            'label'         => trans('api::app.notification.image'),
            'type'          => 'html',
            'searchable'    => false,
            'sortable'      => false,
            'closure'       => true,
            'wrapper'       => function($row) {
                if ( $row->image )
                    return '<img src=' . Storage::url($row->image) . ' class="img-thumbnail" width="100px" height="70px" />';

            }
        ]);

        $this->addColumn([
            'index'         => 'title',
            'label'         => trans('api::app.notification.text-title'),
            'type'          => 'string',
            'searchable'    => true,
            'sortable'      => true,
            'filterable'    => true,
        ]);

        $this->addColumn([
            'index'         => 'content',
            'label'         => trans('api::app.notification.notification-content'),
            'type'          => 'string',
            'searchable'    => true,
            'sortable'      => true,
            'filterable'    => true
        ]);

        $this->addColumn([
            'index'         => 'type',
            'label'         => trans('api::app.notification.notification-type'),
            'type'          => 'string',
            'searchable'    => true,
            'sortable'      => true,
            'filterable'    => true,
            'closure'       => true,
            'wrapper'       => function($row) {
                return ucwords(strtolower(str_replace("_", " ", $row->type)));
            }
        ]);

        $this->addColumn([
            'index'         => 'channel_name',
            'label'         =>  trans('api::app.notification.store-view'),
            'type'          => 'string',
            'searchable'    => false,
            'sortable'      => true,
            'filterable'    => false,
            'closure'       => true,
            'wrapper'       => function($row) {
                $notificationTranslations = app('Webkul\API\Repositories\PushNotificationTranslationRepository')->where(['push_notification_id' => $row->notification_id])->groupBy('push_notification_id', 'channel')->get();

                foreach ($notificationTranslations as $imageChannel) {
                    $channel = app('Webkul\Core\Repositories\ChannelRepository')->findOneByField('code', $imageChannel->channel);
                    if ( $channel ) {
                        echo $channel['name'] . '</br>' . PHP_EOL;
                    } 
                }
            }
        ]);

        $this->addColumn([
            'index'         => 'status',
            'label'         => trans('api::app.notification.notification-status'),
            'type'          => 'number',
            'searchable'    => true,
            'sortable'      => true,
            'filterable'    => true,
            'closure'       => true,
            'wrapper'       => function($row) {
                if ( $row->status == 1 )
                    return '<span class="badge badge-md badge-success">' . trans('api::app.notification.status.enabled') . '</span>';
                else
                    return '<span class="badge badge-md badge-danger">' . trans('api::app.notification.status.disabled') . '</span>';
            }
        ]);

        $this->addColumn([
            'index'         => 'created_at',
            'label'         =>  trans('api::app.notification.created'),
            'type'          => 'datetime',
            'searchable'    => true,
            'sortable'      => true,
            'filterable'    => true
        ]);

        $this->addColumn([
            'index'         => 'updated_at',
            'label'         => trans('api::app.notification.modified'),
            'type'          => 'datetime',
            'searchable'    => true,
            'sortable'      => true,
            'filterable'    => true
        ]);
    }

    /**
     * Prepare actions.
     *
     * @return void
     */
    public function prepareActions()
    {
        $this->addAction([
            'title'     => trans('api::app.datagrid.edit'),
            'method'    => 'GET', //use post only for redirects only
            'route'     => 'api.notification.edit',
            'icon'      => 'icon pencil-lg-icon',
            'condition' => function () {
                return true;
            },
        ]);

        $this->addAction([
            'title'     => trans('api::app.datagrid.delete'),
            'method'    => 'POST', // use GET request only for redirect purposes
            'route'     => 'api.notification.delete',
            'icon'      => 'icon trash-icon',
        ]);
    }

    /**
     * Prepare mass actions.
     *
     * @return void
     */
    public function prepareMassActions()
    {
        $this->addMassAction([
            'type'      => 'delete',
            'title'     => trans('api::app.category.delete'),
            'action'    => route('api.notification.mass-delete'),
            'method'    => 'POST',
        ]);

        $this->addMassAction([
            'type'      => 'update',
            'title'     => trans('api::app.category.update-status'),
            'action'    => route('api.notification.mass-update'),
            'method'    => 'POST',
            'options'   => [
                trans('api::app.category.enabled')   => 1,
                trans('api::app.category.disabled')  => 0
            ]
        ]);
    }
}
