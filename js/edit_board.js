document.addEventListener('DOMContentLoaded', () => {
    const boardIdParam = new URLSearchParams(window.location.search).get('board_id');
    const messageDiv = document.getElementById('message');
    const editBoardForm = document.getElementById('editBoardForm');

    // Элементы формы для удобства
    const boardIdInput = document.getElementById('boardId');
    const boardTitleInput = document.getElementById('boardTitle');
    const boardDescriptionInput = document.getElementById('boardDescription');
    const boardIsPrivateCheckbox = document.getElementById('boardIsPrivate');
    const memberUsernameInput = document.getElementById('memberUsername');
    const memberMessageDiv = document.getElementById('memberMessage');

    // Для списка участников
    const boardMembersList = document.getElementById('boardMembersList');
    const membersMessageDiv = document.getElementById('membersMessage');
    let currentBoardOwnerId = null; // ID владельца доски
    let currentUserId = null; // ID текущего пользователя (если понадобится)
    // isCurrentUserOwner будет храниться в window после get_single

    if (!boardIdParam) {
        if (messageDiv) {
            messageDiv.textContent = 'ID доски не указан в URL.';
            messageDiv.style.color = 'red';
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
                    if (membersMessageDiv) membersMessageDiv.textContent = 'Ошибка формата данных участников.';
                    return;
                }
                if (members.length === 0) {
                    const li = document.createElement('li');
                    li.textContent = 'Участники не добавлены.';
                    boardMembersList.appendChild(li);
                } else {
                    members.forEach(member => {
                        const li = document.createElement('li');
                        let memberDisplay = member.username;
                        if (member.id === currentBoardOwnerId) {
                            memberDisplay += ' (Владелец)';
                        }
                        li.textContent = memberDisplay;

                        // Кнопку удаления показываем, если текущий пользователь - владелец,
                        // и участник не является владельцем.
                        if (window.isCurrentUserOwner && member.id !== currentBoardOwnerId) {
                            const removeButton = document.createElement('button');
                            removeButton.textContent = 'Удалить';
                            removeButton.onclick = () => removeMember(boardIdForMembers, member.id);
                            li.appendChild(removeButton);
                        }
                        boardMembersList.appendChild(li);
                    });
                }
                if (membersMessageDiv) membersMessageDiv.textContent = '';
            })
            .catch(error => {
                console.error('Ошибка загрузки участников:', error);
                if (membersMessageDiv) membersMessageDiv.textContent = error.message;
                boardMembersList.innerHTML = '<li>Не удалось загрузить участников.</li>';
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
                if (membersMessageDiv) {
                    membersMessageDiv.textContent = data.message || 'Участник удален.';
                    membersMessageDiv.style.color = 'green';
                    setTimeout(() => membersMessageDiv.textContent = '', 3000);
                }
                loadBoardMembers(boardIdForRemoval);
            } else {
                if (membersMessageDiv) {
                    membersMessageDiv.textContent = 'Ошибка: ' + (data.error || 'Не удалось удалить участника');
                    membersMessageDiv.style.color = 'red';
                }
            }
        })
        .catch(error => {
            console.error('Ошибка удаления участника:', error);
            if (membersMessageDiv) {
                membersMessageDiv.textContent = error.message;
                membersMessageDiv.style.color = 'red';
            }
        });
    }

    window.addMemberByUsername = function() {
        if (!memberUsernameInput || !boardIdInput || !memberMessageDiv) return;

        const username = memberUsernameInput.value.trim();
        const currentBoardIdVal = boardIdInput.value;

        if (!username) {
            memberMessageDiv.textContent = 'Введите логин участника.';
            memberMessageDiv.style.color = 'red';
            return;
        }
        if (!currentBoardIdVal) {
            memberMessageDiv.textContent = 'ID доски не определен. Обновите страницу.';
            memberMessageDiv.style.color = 'red';
            return;
        }

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
                memberMessageDiv.style.color = 'green';
                memberUsernameInput.value = '';
                setTimeout(() => memberMessageDiv.textContent = '', 3000);
                loadBoardMembers(currentBoardIdVal);
            } else {
                memberMessageDiv.textContent = 'Ошибка при добавлении: ' + (data.error || 'Неизвестная ошибка');
                memberMessageDiv.style.color = 'red';
            }
        })
        .catch(err => {
            console.error("Ошибка добавления участника:", err);
            memberMessageDiv.textContent = err.message || "Произошла сетевая ошибка при добавлении участника.";
            memberMessageDiv.style.color = "red";
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
                        messageDiv.style.color = 'red';
                    }
                    editBoardForm.style.display = 'none';
                    return;
                }
                if (boardIdInput) boardIdInput.value = board.id;
                if (boardTitleInput) boardTitleInput.value = board.title;
                if (boardDescriptionInput) boardDescriptionInput.value = board.description;
                if (boardIsPrivateCheckbox) boardIsPrivateCheckbox.checked = !!parseInt(board.is_private);

                currentBoardOwnerId = board.owner_id;
                window.isCurrentUserOwner = board.is_owner;

                loadBoardMembers(board.id);
            })
            .catch(error => {
                if (messageDiv) {
                    messageDiv.textContent = error.message;
                    messageDiv.style.color = 'red';
                }
                editBoardForm.style.display = 'none';
                console.error('Ошибка загрузки данных доски:', error);
            });
    }

    if (editBoardForm) {
        editBoardForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!boardIdInput || !boardTitleInput || !boardDescriptionInput || !boardIsPrivateCheckbox || !messageDiv) return;

            const data = {
                id: boardIdInput.value,
                title: boardTitleInput.value.trim(),
                description: boardDescriptionInput.value.trim(),
                is_private: boardIsPrivateCheckbox.checked
            };

            if (!data.title) {
                messageDiv.textContent = 'Название доски не может быть пустым.';
                messageDiv.style.color = 'red';
                return;
            }

            fetch('api/boards.php?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(response => {
                if (response.success) {
                    messageDiv.textContent = 'Доска успешно обновлена!';
                    messageDiv.style.color = 'green';
                    setTimeout(() => {
                        // Можно не перенаправлять сразу, а просто показать сообщение
                        // window.location.href = 'dashboard.php';
                    }, 1500);
                } else {
                    messageDiv.textContent = 'Ошибка обновления: ' + (response.error || 'Неизвестная ошибка');
                    messageDiv.style.color = 'red';
                }
            })
            .catch(err => {
                console.error('Ошибка обновления доски:', err);
                messageDiv.textContent = 'Произошла ошибка сети при обновлении.';
                messageDiv.style.color = 'red';
            });
        });
    }
});
