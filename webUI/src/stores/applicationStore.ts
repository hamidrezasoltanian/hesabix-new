import {defineStore} from "pinia";

export const applicationStore = defineStore('application',{
    state () {
        return {
            title: 'app.name',
            activeBid: localStorage.getItem('activeBid') ?? '',
            activeYear: localStorage.getItem('activeYear') ?? '',
            activeMoney: localStorage.getItem('activeMoney') ?? '',
        }
    },
    actions: {
        setTitle (state:any, title:String) {
            state.title = title;
        },
        getTitle () {
            return this.$state.title;
        }
    }
});

export const useApplicationStore = applicationStore;
export default applicationStore;