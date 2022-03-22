import MainDashboard from "@oxygen-cms/ui/src/components/dashboard/MainDashboard.vue";
import Vue from 'vue';
import OxygenUI from '@oxygen-cms/ui/src/main';
import Media from '@oxygen-cms/ui/src/modules/Media';
import UserManagement from '@oxygen-cms/ui/src/modules/UserManagement';
import PagesPartials from '@oxygen-cms/ui/src/modules/PagesPartials';
// import Events from '@oxygen-cms/ui/src/modules/Events';
import PagesPartialsPanel from '@oxygen-cms/ui/src/components/dashboard/PagesPartialsPanel.vue';
import EventsPanel from '@oxygen-cms/ui/src/components/dashboard/EventsPanel.vue';
import MediaPanel from '@oxygen-cms/ui/src/components/dashboard/MediaPanel.vue';

const ui = new OxygenUI(Vue);

ui.registerModule(UserManagement);
ui.registerModule(PagesPartials);
// ui.registerModule(Events);
ui.registerModule(Media);

ui.addRoute({
    name: 'dashboard',
    path: 'dashboard',
    component: MainDashboard,
    props: {
        panels: [
            [
                { permission: 'pages.getList', component: PagesPartialsPanel, name: 'pages-partials' }
            ],
            [
                { permission: 'media.getList', component: MediaPanel, name: 'media' }
            ],
            [
                // { permission: 'events.getList', component: EventsPanel, name: 'events' }
            ]
        ],
        extraRows: []
    },
    meta: { title: 'Dashboard' }
});

ui.createApp().mount('#app')
