import avatar1 from "@/assets/images/users/avatar-1.jpg"
import avatar2 from "@/assets/images/users/avatar-2.jpg"
import avatar3 from "@/assets/images/users/avatar-3.jpg"
import avatar4 from "@/assets/images/users/avatar-4.jpg"
import avatar5 from "@/assets/images/users/avatar-5.jpg"
import avatar6 from "@/assets/images/users/avatar-6.jpg"
import avatar7 from "@/assets/images/users/avatar-7.jpg"
import avatar8 from "@/assets/images/users/avatar-8.jpg"

const todoTasks = [
    {
        id: '#NZ1220',
        title: 'Admin layout design',
        text: 'Sed ut perspiciatis unde',
        date: '17 Apr, 2024',
        user: [avatar3, avatar2],
        groupId: 1
    },
    {
        id: '#NZ1219',
        title: 'Chat app page',
        text: 'Neque porro quisquam est',
        date: '15 Apr, 2019',
        user: [avatar6],
        groupId: 1
    },
    {
        id: '#NZ1218',
        title: 'Ecommerce App pages',
        date: '12 Apr, 2019',
        text: 'Itaque earum rerum hic',
        user: [avatar5],
        groupId: 1
    },
];

const progressTasks = [
    {
        id: '#NZ1217',
        title: 'Dashboard UI',
        text: 'In enim justo, rhoncus ut',
        date: '05 Apr, 2024',
        user: [avatar6],
        groupId: 2
    },
    {
        id: '#NZ1216',
        title: 'Authentication pages',
        text: 'Imperdiet Etiam ultricies',
        date: '02 Apr, 2024',
        user: [avatar8, avatar1],
        groupId: 2
    },
    {
        id: '#NZ1215',
        title: 'UI Elements pages',
        text: 'Cras ultricies mi eu turpis',
        date: '28 Mar, 2024',
        user: [avatar2],
        groupId: 2
    },
];

const completedTasks = [
    {
        id: '#NZ1214',
        title: 'Brand logo design',
        text: 'Aenean leo ligula, porttitor eu',
        date: '24 Mar, 2024',
        user: [avatar4],
        groupId: 3
    },
    {
        id: '#NZ1213',
        title: 'Email pages',
        text: 'It will be as simple as Occidental',
        date: '20 Mar, 2024',
        user: [avatar6, avatar7],
        groupId: 3
    },
    {
        id: '#NZ1212',
        title: 'Forms pages',
        text: 'Donec quam felis, ultricies nec',
        date: '14 Mar, 2019',
        user: [avatar5],
        groupId: 3
    },
];

export { todoTasks, progressTasks, completedTasks };

