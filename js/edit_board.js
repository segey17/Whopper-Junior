document.addEventListener('DOMContentLoaded', () => {
    const boardIdParam = new URLSearchParams(window.location.search).get('board_id');
    const messageDiv = document.getElementById('message');
    const editBoardForm = document.getElementById('editBoardForm');

    // Элементы формы для удобства
    const boardIdInput = document.getElementById('boardId');
    const boardTitleInput = document.getElementById('boardTitle');
    const boardDescriptionInput = document.getElementById('boardDescription');
    const memberUsernameInput = document.getElementById('memberUsername');
    const memberMessageDiv = document.getElementById('memberMessage');

    // Для списка участников
    const boardMembersList = document.getElementById('boardMembersList');
    const membersMessageDiv = document.getElementById('membersMessage'); // This is the message div for the list of members itself
    const boardNameSubtitleElement = document.getElementById('boardNameSubtitle'); // For the board name in the subtitle
    let currentBoardOwnerId = null; // ID владельца доски
    let currentUserId = null; // ID текущего пользователя (если понадобится)
    // isCurrentUserOwner будет храниться в window после get_single

    function showToast(message, type = 'info') {
        const toast = document.getElementById('toast-notification');
        if (!toast) return;

        toast.textContent = message;
        toast.className = 'toast-notification'; // Reset classes
        if (type === 'success') {
            toast.classList.add('success');
        } else if (type === 'error') {
            toast.classList.add('error');
        }
        // else, it's an info/default toast

        toast.classList.add('show');

        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }

    if (!boardIdParam) {
        if (messageDiv) {
            messageDiv.textContent = 'ID доски не указан в URL.';
            messageDiv.style.color = 'var(--danger-color, red)';
        }
        if (editBoardForm) editBoardForm.style.display = 'none';
        return;
    }

    // Функция загрузки участников доски
    window.loadBoardMembers = function(boardIdForMembers) {
        if (!boardMembersList || !boardIdForMembers) return;

        fetch(`api/boards.php?action=get_members&board_id=${boardIdForMembers}`)
            .then(res => {
                if (!res.ok) return res.json().then(err => { throw new Error(err.error || 'Ошибка загрузки участников'); });
                return res.json();
            })
            .then(members => {
                boardMembersList.innerHTML = '';
                if (!Array.isArray(members)) {
                    if (membersMessageDiv) {
                        membersMessageDiv.textContent = 'Ошибка формата данных участников.';
                        membersMessageDiv.style.color = 'var(--danger-color, red)';
                    }
                    return;
                }
                if (members.length === 0) {
                    const li = document.createElement('li');
                    li.innerHTML = `<div class="member-info"><i class="fas fa-info-circle"></i><span>Участники не добавлены.</span></div>`;
                    li.style.justifyContent = 'center';
                    li.style.padding = '15px';
                    boardMembersList.appendChild(li);
                } else {
                    members.forEach(member => {
                        const li = document.createElement('li');

                        const memberInfoDiv = document.createElement('div');
                        memberInfoDiv.classList.add('member-info');

                        const icon = document.createElement('i');
                        icon.classList.add('fas', member.id === currentBoardOwnerId ? 'fa-user-crown' : 'fa-user');
                        memberInfoDiv.appendChild(icon);

                        const usernameSpan = document.createElement('span');
                        usernameSpan.classList.add('member-username');
                        usernameSpan.textContent = member.username;
                        memberInfoDiv.appendChild(usernameSpan);

                        if (member.id === currentBoardOwnerId) {
                            const ownerSpan = document.createElement('span');
                            ownerSpan.classList.add('member-role');
                            ownerSpan.textContent = '(Владелец)';
                            memberInfoDiv.appendChild(ownerSpan);
                        } else if (member.role) { // Отображаем другую роль, если есть и не владелец
                            const roleSpan = document.createElement('span');
                            roleSpan.classList.add('member-role');
                            roleSpan.textContent = `(${member.role})`;
                            memberInfoDiv.appendChild(roleSpan);
                        }

                        li.appendChild(memberInfoDiv);

                        if (window.isCurrentUserOwner && member.id !== currentBoardOwnerId) {
                            const removeButton = document.createElement('button');
                            removeButton.classList.add('remove-member-btn'); // Этот класс уже должен применяться из CSS
                            removeButton.innerHTML = '<i class="fas fa-trash-alt"></i> Удалить'; // Иконка корзины
                            removeButton.onclick = () => removeMember(boardIdForMembers, member.id);
                            li.appendChild(removeButton);
                        }
                        boardMembersList.appendChild(li);
                    });
                }
                if (membersMessageDiv) membersMessageDiv.textContent = ''; // Clear previous messages
            })
            .catch(error => {
                console.error('Ошибка загрузки участников:', error);
                if (membersMessageDiv) {
                    membersMessageDiv.textContent = error.message;
                    membersMessageDiv.style.color = 'var(--danger-color, red)';
                }
                boardMembersList.innerHTML = '<li><div class="member-info"><i class="fas fa-exclamation-triangle"></i><span>Не удалось загрузить участников.</span></div></li>';
            });
    }

    // Функция удаления участника
    window.removeMember = function(boardIdForRemoval, userIdToRemove) {
        if (!confirm(`Вы уверены, что хотите удалить этого участника?`)) return;

        fetch('api/boards.php?action=remove_member', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ board_id: boardIdForRemoval, user_id: userIdToRemove })
        })
        .then(res => {
            if (!res.ok) return res.json().then(err => { throw new Error(err.error || 'Ошибка удаления участника'); });
            return res.json();
        })
        .then(data => {
            if (data.success) {
                if (memberMessageDiv) { // Используем основной memberMessageDiv для обратной связи об удалении
                    memberMessageDiv.textContent = data.message || 'Участник удален.';
                    memberMessageDiv.style.color = 'var(--success-color, green)';
                    setTimeout(() => memberMessageDiv.textContent = '', 3000);
                }
                loadBoardMembers(boardIdForRemoval);
            } else {
                if (memberMessageDiv) {
                    memberMessageDiv.textContent = 'Ошибка: ' + (data.error || 'Не удалось удалить участника');
                    memberMessageDiv.style.color = 'var(--danger-color, red)';
                }
            }
        })
        .catch(error => {
            console.error('Ошибка удаления участника:', error);
            if (memberMessageDiv) {
                memberMessageDiv.textContent = error.message;
                memberMessageDiv.style.color = 'var(--danger-color, red)';
            }
        });
    }

    window.addMemberByUsername = function() {
        if (!memberUsernameInput || !boardIdInput || !memberMessageDiv) return;

        const username = memberUsernameInput.value.trim();
        const currentBoardIdVal = boardIdInput.value;

        if (!username) {
            memberMessageDiv.textContent = 'Введите логин участника.';
            memberMessageDiv.style.color = 'var(--danger-color, red)';
            return;
        }
        if (!currentBoardIdVal) {
            memberMessageDiv.textContent = 'ID доски не определен. Обновите страницу.';
            memberMessageDiv.style.color = 'var(--danger-color, red)';
            return;
        }
        memberMessageDiv.textContent = 'Добавление...'; // Indicate processing
        memberMessageDiv.style.color = 'var(--text-color, #333)';

        fetch('api/boards.php?action=add_member', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ board_id: currentBoardIdVal, username: username })
        })
        .then(res => {
            if (!res.ok) {
                return res.json().then(errData => {
                    throw new Error(errData.error || `Ошибка ${res.status}`);
                }).catch(() => {
                    throw new Error(`Ошибка ${res.status}: ${res.statusText}`);
                });
            }
            return res.json();
        })
        .then(data => {
            if (data.success) {
                memberMessageDiv.textContent = data.message || 'Участник успешно добавлен!';
                memberMessageDiv.style.color = 'var(--success-color, green)';
                memberUsernameInput.value = '';
                setTimeout(() => memberMessageDiv.textContent = '', 3000);
                loadBoardMembers(currentBoardIdVal);
            } else {
                memberMessageDiv.textContent = 'Ошибка при добавлении: ' + (data.error || 'Неизвестная ошибка');
                memberMessageDiv.style.color = 'var(--danger-color, red)';
            }
        })
        .catch(err => {
            console.error("Ошибка добавления участника:", err);
            memberMessageDiv.textContent = err.message || "Произошла сетевая ошибка при добавлении участника.";
            memberMessageDiv.style.color = "var(--danger-color, red)";
        });
    }

    if (boardIdParam && editBoardForm) {
        fetch(`api/boards.php?action=get_single&board_id=${boardIdParam}`)
            .then(res => {
                if (!res.ok) {
                    return res.json().then(errData => {
                        throw new Error(errData.error || `Ошибка ${res.status}: ${res.statusText}`);
                    }).catch(() => {
                        throw new Error(`Ошибка ${res.status}: ${res.statusText}`);
                    });
                }
                return res.json();
            })
            .then(board => {
                if (!board || board.error) {
                    if (messageDiv) {
                        messageDiv.textContent = board.error || 'Доска не найдена или нет доступа.';
                        messageDiv.style.color = 'var(--danger-color, red)';
                    }
                    if (boardNameSubtitleElement) boardNameSubtitleElement.textContent = 'Ошибка загрузки доски';
                    editBoardForm.style.display = 'none';
                    // Также скрыть секцию участников, если доска не загружена
                    const membersSection = document.querySelector('.board-members-section');
                    if(membersSection) membersSection.style.display = 'none';
                    return;
                }
                if (boardIdInput) boardIdInput.value = board.id;
                if (boardTitleInput) boardTitleInput.value = board.title;
                if (boardDescriptionInput) boardDescriptionInput.value = board.description;
                if (boardNameSubtitleElement) {
                    boardNameSubtitleElement.textContent = `Настройки для доски "${board.title || 'Без названия'}"`;
                }

                currentBoardOwnerId = board.owner_id;
                window.isCurrentUserOwner = board.is_owner; // is_owner должно приходить от API get_single

                // Управляем видимостью форм и секций на основе того, является ли пользователь владельцем
                const formSection = document.querySelector('.board-edit-form-section');
                const membersManagementSection = document.querySelector('.board-members-section .board-form-card > .input-group'); // Конкретно поле добавления

                if (window.isCurrentUserOwner) {
                    if (formSection) formSection.style.display = '';
                    if (membersManagementSection) membersManagementSection.style.display = ''; // Показываем управление участниками
                } else {
                    if (formSection) formSection.style.display = 'none'; // Скрываем форму редактирования доски
                    if (membersManagementSection) membersManagementSection.style.display = 'none'; // Скрываем добавление участников
                    if (messageDiv) {
                        messageDiv.textContent = 'У вас нет прав для редактирования этой доски или управления участниками.';
                        messageDiv.style.color = 'var(--warning-color, orange)';
                    }
                }

                loadBoardMembers(board.id);
            })
            .catch(error => {
                if (messageDiv) {
                    messageDiv.textContent = error.message;
                    messageDiv.style.color = 'var(--danger-color, red)';
                }
                if (boardNameSubtitleElement) boardNameSubtitleElement.textContent = 'Ошибка загрузки доски';
                editBoardForm.style.display = 'none';
                const membersSection = document.querySelector('.board-members-section');
                if(membersSection) membersSection.style.display = 'none';
                console.error('Ошибка загрузки данных доски:', error);
            });
    }

    if (editBoardForm) {
        editBoardForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!boardIdInput || !boardTitleInput || !boardDescriptionInput || !messageDiv) return;

            const data = {
                id: boardIdInput.value,
                title: boardTitleInput.value.trim(),
                description: boardDescriptionInput.value.trim()
            };

            if (!data.title) {
                messageDiv.textContent = 'Название доски не может быть пустым.';
                messageDiv.style.color = 'var(--danger-color, red)';
                return;
            }
            messageDiv.textContent = 'Сохранение...'; // Indicate processing
            messageDiv.style.color = 'var(--text-color, #333)';

            fetch('api/boards.php?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(response => {
                if (response.success) {
                    // messageDiv.textContent = 'Доска успешно обновлена!';
                    // messageDiv.style.color = 'var(--success-color, green)';
                    showToast('Настройки доски успешно сохранены!', 'success');

                    if (boardNameSubtitleElement && data.title) {
                         // boardNameSubtitleElement.textContent = `Настройки для доски "${data.title}"`;
                         // Подзаголовок теперь статический в HTML, так что эту строку можно убрать или переосмыслить
                    }
                    // setTimeout(() => {
                    //      messageDiv.textContent = ''; // Clear message after a delay
                    // }, 3000);
                } else {
                    // messageDiv.textContent = 'Ошибка обновления: ' + (response.error || 'Неизвестная ошибка');
                    // messageDiv.style.color = 'var(--danger-color, red)';
                    showToast('Ошибка обновления: ' + (response.error || 'Неизвестная ошибка'), 'error');
                }
            })
            .catch(err => {
                console.error('Ошибка обновления доски:', err);
                // messageDiv.textContent = 'Произошла ошибка сети при обновлении.';
                // messageDiv.style.color = 'var(--danger-color, red)';
                showToast('Произошла ошибка сети при обновлении.', 'error');
            });
        });
    }
});
