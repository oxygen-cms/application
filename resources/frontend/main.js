import Preferences from '@oxygen-cms/ui/src/components/preferences/Preferences.vue';
import MainDashboard from "@oxygen-cms/ui/src/components/dashboard/MainDashboard.vue";
import Vue from 'vue';
import OxygenUI from '@oxygen-cms/ui/src/main';
import Media from '@oxygen-cms/ui/src/modules/Media';
import UserManagement from '@oxygen-cms/ui/src/modules/UserManagement';
import LegacyPages from '@oxygen-cms/ui/src/modules/LegacyPages';
import PagesPartialsPanel from '@oxygen-cms/ui/src/components/dashboard/PagesPartialsPanel.vue';
import MediaPanel from '@oxygen-cms/ui/src/components/dashboard/MediaPanel.vue';
import EventsPanel from '@oxygen-cms/ui/src/components/dashboard/EventsPanel.vue';

const ui = new OxygenUI(Vue);

ui.registerModule(UserManagement);
ui.registerModule(LegacyPages);
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
                { permission: 'upcomingEvents.getList', component: EventsPanel, name: 'events' }
            ]
        ],
        extraRows: []
    },
    meta: { title: 'Dashboard' }
});

ui.addRoute({
    path: 'preferences',
    component: Preferences,
    props: {
        extraPrefs: {
            'appearance': [],
            'external': []
        }
    },
    meta: { title: 'System Preferences' }
});

ui.createApp().mount('#app')
