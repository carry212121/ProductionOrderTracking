import './bootstrap';

import Alpine from 'alpinejs';
import './events/searchEvent';
import './events/product-searchEvent';
import { setupFilterEvents } from './events/filterEvents';

setupFilterEvents(
    'filterToggleBtn',
    'filterPanel',
    '.filter-option',
    '.product-card'
);

window.Alpine = Alpine;

Alpine.start();

import Swal from 'sweetalert2';

document.addEventListener("DOMContentLoaded", () => {
    if (!window.LaravelUserId) return;
    console.log("📡 Echo load?:", Echo);
    console.log("👤 LaravelUserId:", window.LaravelUserId);

    if (typeof Echo !== 'undefined' && window.LaravelUserId) {
        Echo.private(`App.Models.User.${window.LaravelUserId}`)
            .notification((notification) => {
                console.log("📢 Notification:", notification);

                // ✅ Toast popup
                Toastify({
                    text: notification.message,
                    duration: 5000,
                    gravity: "bottom",
                    position: "right",
                    stopOnFocus: true,
                    style: {
                        background: "#9ca3af", 
                        color: "#ffffff",
                        borderRadius: "4px",    
                        padding: "16px 20px",
                        width: "300px",         
                        height: "80px",         
                        display: "flex",
                        alignItems: "center",
                    }
                }).showToast();
                Alpine.store('notifications').unreadCount++;
            });
    }
});

document.addEventListener("DOMContentLoaded", () => {
    if (!window.LaravelUserId) return;
    const notifList = document.getElementById('notificationList');

    // ✅ Load unread count immediately
    fetch('/notifications')
        .then(res => res.json())
        .then(data => {
            const unread = data.filter(n => n.read_at === null).length;
            Alpine.store('notifications').unreadCount = unread;
        })
        .catch(error => {
            console.error("❌ Failed to load notifications count:", error);
        });

    // ✅ Load notifications when bell is clicked
    const notifButton = document.querySelector('[data-notif-button]');
    if (notifButton && notifList) {
        notifButton.addEventListener('click', () => {
            // Only load once
            if (notifList.childElementCount > 0) return;

            fetch('/notifications')
                .then(res => res.json())
                .then(data => {
                    data.forEach(notification => {
                        const isUnread = notification.read_at === null;
                        if (isUnread && isOlderThan2Days(notification.created_at)) {
                            return; // skip adding to the DOM
                        }
                        const li = document.createElement("li");

                        li.className = `p-3 hover:bg-gray-100 cursor-pointer ${
                            isUnread ? 'bg-indigo-50 font-bold' : 'text-gray-500'
                        }`;

                        li.setAttribute('data-id', notification.id);
                        li.setAttribute('data-url', notification.data.url);

                        // Add a small dot for unread
                        const severityColor = {
                            yellow: 'bg-yellow-400',
                            red: 'bg-red-500',
                            darkred: 'bg-red-800',
                        };
                        const isLateProduct = notification.type === 'App\\Notifications\\LateProductJob';
                        const severity = notification.data?.severity;
                        const dotClass = isLateProduct && severity ? severityColor[severity] ?? 'bg-gray-300' : null;

                        if (isLateProduct) {
                            // console.log(`📦 Notification: ${notification.data.message}`);
                            // console.log(`🔸 Severity: ${severity}`);
                            // console.log(`🎨 Dot Color Class: ${dotClass}`);
                        }

                        const dot = (notification.type === 'App\\Notifications\\LateProductJob' && notification.data.severity)
                            ? `<span class="w-4 h-4 rounded-full ${severityColor[notification.data.severity] ?? ''} inline-block mr-2 border border-black"></span>`
                            : '';
                        // console.log(`🎨 Dot Color: ${dot}`);
                        li.innerHTML = `
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center justify-between">
                                    <div class="text-sm ${isUnread ? 'text-gray-900' : 'text-gray-600'}">
                                        ${dot}${notification.data.message}
                                    </div>
                                </div>
                                <div class="text-xs text-gray-400">${timeAgo(notification.created_at)}</div>
                            </div>
                        `;


                        li.addEventListener('click', function () {
                            const url = this.getAttribute('data-url');
                            const notifId = this.getAttribute('data-id');

                            // Mark as read
                            fetch(`/notifications/${notifId}/read`, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                    'Content-Type': 'application/json',
                                }
                            })
                                .then(response => {
                                    if (!response.ok) throw new Error("Mark as read failed");

                                    Alpine.store('notifications').unreadCount--;
                                    this.classList.remove('bg-indigo-50', 'font-bold');
                                    this.classList.add('text-gray-500');
                                    this.querySelector('span')?.remove();

                                    if (url) window.location.href = url;
                                })
                                .catch(err => {
                                    console.error("❌ Failed to mark as read:", err);
                                });
                        });

                        notifList.appendChild(li);
                    });
                })
                .catch(error => {
                    console.error("❌ Error loading notifications:", error);
                });
        });
    }
});

function isOlderThan2Days(createdAt) {
    const created = new Date(createdAt);
    const now = new Date();
    const diffTime = Math.abs(now - created);
    const diffDays = diffTime / (1000 * 60 * 60 * 24);
    return diffDays > 2;
}

function timeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);

    const intervals = {
        day: 86400,
        hour: 3600,
        minute: 60,
    };

    if (seconds >= intervals.day) {
        const days = Math.floor(seconds / intervals.day);
        return `${days} วันก่อน`; // days ago in Thai
    } else if (seconds >= intervals.hour) {
        const hours = Math.floor(seconds / intervals.hour);
        return `${hours} ชั่วโมงก่อน`; // hours ago
    } else if (seconds >= intervals.minute) {
        const minutes = Math.floor(seconds / intervals.minute);
        return `${minutes} นาทีที่แล้ว`; // minutes ago
    } else {
        return `เมื่อสักครู่`; // just now
    }
}





