
import avatar2 from "@/assets/images/users/avatar-2.jpg";
import avatar3 from "@/assets/images/users/avatar-3.jpg";
import avatar4 from "@/assets/images/users/avatar-4.jpg";
import avatar6 from "@/assets/images/users/avatar-6.jpg";

const chatData = [
    {
        id: 1,
        image: avatar2,
        name: 'Frank Vickery',
        message: 'Hey! there I\'m available',
        time: '04    min',
        status: 'online'
    },
    {
        id: 2,
        image: avatar3,
        name: 'Robert Winter',
        message: 'I\'ve finished it! See you so',
        time: '09 min',
        status: 'away'
    },
    {
        id: 3,
        name: 'Crystal Elliott',
        message: 'This theme is awesome!',
        time: '21 min',
        status: 'online'
    },
    {
        id: 4,
        image: avatar4,
        name: 'Kristen Steele',
        message: 'Nice to meet you',
        time: '1 hr',
    },
    {
        id: 5,
        name: 'Mitchel Givens',
        message: 'Hey! there I\'m available',
        time: '3 hrs',
        status: 'away'
    },
    {
        id: 6,
        image: avatar6,
        name: 'Stephen Hadley',
        message: 'I\'ve finished it! See you so',
        time: '5 hrs',
        status: 'online'
    },
    {
        id: 7,
        image: avatar2,
        name: 'Tracy Penley',
        message: 'This theme is awesome!',
        time: '24/03',
        status: 'online'
    },
];  

const chatMessagesData = [
    {
        name: 'Frank Vickery',
        image: avatar2,
        message: 'Hello!',
        time: '10:00'
    },
    {
        align: 'right',
        name: 'Henry Wells',
        message: 'Hi, How are you? What about our next meeting?',
        time: '10:02'
    },
    {
        name: 'Frank Vickery',
        image: avatar2,
        message: 'Yeah everything is fine',
        time: '10:06'
    },
    {
        name: 'Frank Vickery',
        image: avatar2,
        message: '& Next meeting tomorrow 10.00AM',
        time: '10:06'
    },
    {
        align: 'right',
        name: 'Henry Wells',
        message: 'Wow that\'s great',
        time: '10:07'
    }
];

export { chatData, chatMessagesData };