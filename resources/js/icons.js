import {
    createIcons, GraduationCap, HeartHandshake, Users, ShieldCheck,
    ArrowRight, ArrowLeft, ArrowUpRight, ArrowUp, ArrowDown, ChevronDown,
    Check, Clock, Mail, MapPin, LayoutDashboard, FileText, Menu, LockKeyhole,
    Pencil, Plus, Trash2, Eye, Search, LogOut, Files, Newspaper, PanelsTopLeft,
    ListTree, Images, Settings2, ClipboardList, Tags, Database, CircleHelp, Ellipsis, Sparkles,
} from 'lucide';

const icons = {
    GraduationCap, HeartHandshake, Users, ShieldCheck, ArrowRight, ArrowLeft,
    ArrowUpRight, ArrowUp, ArrowDown, ChevronDown, Check, Clock, Mail, MapPin,
    LayoutDashboard, FileText, Menu, LockKeyhole, Pencil, Plus, Trash2, Eye,
    Search, LogOut, Files, Newspaper, PanelsTopLeft, ListTree, Images,
    Settings2, ClipboardList, Tags, Database, CircleHelp, Ellipsis, Sparkles,
};

export function renderIcons(root = document) {
    createIcons({ icons, root, inTemplates: true, attrs: { 'stroke-width': 1.75, 'aria-hidden': 'true', focusable: 'false' } });
}
