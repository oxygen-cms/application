import MainDashboard from "@oxygen-cms/ui/src/components/dashboard/MainDashboard.vue";
import Vue from 'vue';
import OxygenUI from '@oxygen-cms/ui/src/main';
import Media from '@oxygen-cms/ui/src/modules/Media';
import UserManagement from '@oxygen-cms/ui/src/modules/UserManagement';
import PagesPartials from '@oxygen-cms/ui/src/modules/PagesPartials';
import PagesPartialsPanel from '@oxygen-cms/ui/src/components/dashboard/PagesPartialsPanel.vue';
import MediaPanel from '@oxygen-cms/ui/src/components/dashboard/MediaPanel.vue';

const ui = new OxygenUI(Vue);

ui.registerModule(UserManagement);
ui.registerModule(PagesPartials);
ui.registerModule(Media);

ui.addRoute({
    name: 'dashboard',
    path: 'dashboard',
    component: MainDashboard,
    props: {
        panels: [
            [
                { permission: 'pages.getList', component: PagesPartialsPanel, name: 'pages-partials' },
                { permission: 'media.getList', component: MediaPanel, name: 'media' }
            ]
        ],
        extraRows: []
    },
    meta: { title: 'Dashboard' }
});

const app = ui.createApp();
app.mount('#app');

// Set up callback for iframe content pages to notify parent SPA
window.Oxygen = {
    onLoadedPageInsideIframe: function(pageId) {
        console.log('[Oxygen] Page loaded in iframe:', pageId);
        const currentRoute = app.app.$route;
        // Only navigate if we're not already on the correct page edit view
        if (currentRoute.name !== 'pages.edit' || currentRoute.params.id != pageId) {
            console.log('[Oxygen] Navigating to edit page:', pageId);
            // Preserve query params from current route
            app.app.$router.push({
                name: 'pages.edit',
                params: { id: pageId },
                query: currentRoute.query
            });
        } else {
            console.log('[Oxygen] Already on correct page, skipping navigation');
        }
    }
};
