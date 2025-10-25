/* global window, document, fetch */
(function () {
    'use strict';

    var config = window.NV_FEED_CONFIG || {};
    var currentUserId = Number(config.currentUserId || 0);
    var isAdmin = !!config.isAdmin;
    var emojiMap = config.emoji || {};
    var defaultReactions = Object.keys(emojiMap);
    var apiEndpoint = 'ajax.php';

    if (!currentUserId) {
        return;
    }

    function sendRequest(method, data) {
        return fetch(apiEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                method: method,
                data: data
            }),
            credentials: 'same-origin'
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        });
    }

    function buildReactionSummaryHTML(summary) {
        if (!summary) {
            return '';
        }
        var html = '';
        defaultReactions.forEach(function (key) {
            var count = Number(summary[key] || 0);
            if (count > 0 && Object.prototype.hasOwnProperty.call(emojiMap, key)) {
                html += '<span class="reaction-item" title="' + key.charAt(0).toUpperCase() + key.slice(1) + '">' +
                    emojiMap[key] + ' <span class="reaction-count">' + count + '</span></span> ';
            }
        });
        return html.trim();
    }

    function refreshReactionSummary(interactionType, targetId, summaryContainer) {
        if (!summaryContainer) {
            return;
        }
        var method;
        var payload;
        if (interactionType === 'discussion') {
            method = 'get_discussion_reactions';
            payload = { discussion_id: targetId };
        } else if (interactionType === 'item') {
            method = 'get_item_reactions';
            payload = { item_id: targetId };
        } else {
            return;
        }

        sendRequest(method, payload).then(function (result) {
            if (result && result.success) {
                var html = buildReactionSummaryHTML(result.reactions || {});
                summaryContainer.innerHTML = html;
            }
        }).catch(function (error) {
            console.error('Failed to refresh reactions:', error);
        });
    }

    function makeAvatarHTML(userId, avatarUrl) {
        if (userId > 0) {
            var src = avatarUrl ? escapeHtml(avatarUrl) : 'images/default_avatar.webp';
            return '<img src="' + src + '" alt="User avatar" class="feed-comment-avatar-img avatar-img-sm">';
        }
        return '<div class="feed-comment-avatar-placeholder">?</div>';
    }

    function appendComment(commentList, emptyNotice, commentData, interactionType) {
        if (!commentList || !commentData) {
            return;
        }
        if (emptyNotice) {
            emptyNotice.classList.add('hidden');
        }
        commentList.classList.remove('hidden');

        var commentId = Number(commentData.id || 0);
        var commentUserId = Number(commentData.user_id || 0);
        var fullName = (commentData.first_name || '') + ' ' + (commentData.last_name || '');
        fullName = fullName.trim();
        var displayName = fullName.length ? fullName : 'Member';
        var createdAt = commentData.created_at || '';
        var text = commentData.comment || '';
        var avatar = commentData.avatar || '';
        var canDelete = (commentUserId === currentUserId) || isAdmin;

        var timeLabel = 'Just now';
        if (createdAt) {
            try {
                var date = new Date(createdAt.replace(' ', 'T'));
                if (!isNaN(date.getTime())) {
                    timeLabel = date.toLocaleString();
                }
            } catch (e) {
                timeLabel = createdAt;
            }
        }

        var html = ''
            + '<div class="feed-comment flex items-start gap-3" data-comment-id="' + commentId + '" data-user-id="' + commentUserId + '">'
            + '  <div class="feed-comment-avatar">'
            +        makeAvatarHTML(commentUserId, avatar)
            + '  </div>'
            + '  <div class="feed-comment-body flex-1">'
            + '      <div class="feed-comment-meta text-xs text-gray-500 flex items-center gap-2">'
            + '          <span class="font-semibold text-gray-700">' + escapeHtml(displayName) + '</span>'
            + '          <span class="feed-comment-timestamp">' + escapeHtml(timeLabel) + '</span>';
        if (canDelete) {
            html += '          <button type="button" class="feed-comment-delete ml-auto text-gray-400 hover:text-warm-red" title="Delete comment" data-comment-delete>&times;</button>';
        }
        html += '      </div>'
            + '      <div class="feed-comment-text text-sm text-gray-700">' + formatCommentText(text) + '</div>'
            + '  </div>'
            + '</div>';

        commentList.insertAdjacentHTML('beforeend', html);
    }

    function escapeHtml(input) {
        if (input === null || input === undefined) {
            return '';
        }
        return String(input)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatCommentText(text) {
        var safe = escapeHtml(text);
        return safe.replace(/\n/g, '<br>');
    }

    function handleReactionClick(event) {
        var button = event.target.closest('.reaction-btn');
        if (!button) {
            return;
        }
        var reactionsContainer = button.closest('.feed-reactions');
        var wrapper = button.closest('[data-feed-interaction]');
        if (!reactionsContainer || !wrapper) {
            return;
        }

        event.preventDefault();

        var interactionType = wrapper.getAttribute('data-feed-interaction');
        var targetIdAttr = interactionType === 'discussion' ? 'data-discussion-id' : 'data-item-id';
        var targetId = Number(wrapper.getAttribute(targetIdAttr) || 0);
        if (!targetId) {
            return;
        }

        var reaction = button.getAttribute('data-reaction');
        if (!reaction) {
            return;
        }

        var payload = { user_id: currentUserId };
        var method;

        if (interactionType === 'discussion') {
            payload.discussion_id = targetId;
            if (reaction === 'remove') {
                method = 'remove_discussion_reaction';
            } else {
                method = 'react_to_discussion';
                payload.reaction = reaction;
            }
        } else if (interactionType === 'item') {
            payload.item_id = targetId;
            if (reaction === 'remove') {
                method = 'remove_item_reaction';
            } else {
                method = 'react_to_item';
                payload.reaction = reaction;
                var identifier = reactionsContainer.getAttribute('data-item-identifier');
                if (identifier) {
                    payload.item_identifier = Number(identifier);
                }
            }
        } else {
            return;
        }

        sendRequest(method, payload).then(function (result) {
            if (result && result.success !== false) {
                var summary = reactionsContainer.querySelector('.reaction-summary');
                refreshReactionSummary(interactionType, targetId, summary);
            } else {
                console.error('Failed to update reaction', result);
            }
        }).catch(function (error) {
            console.error('Reaction request failed:', error);
        });
    }

    function handleCommentSubmit(event) {
        var form = event.target;
        if (!form.classList || !form.classList.contains('feed-comment-form')) {
            return;
        }
        event.preventDefault();

        var wrapper = form.closest('[data-feed-interaction]');
        if (!wrapper) {
            return;
        }

        var interactionType = wrapper.getAttribute('data-feed-interaction');
        var targetIdAttr = interactionType === 'discussion' ? 'data-discussion-id' : 'data-item-id';
        var targetId = Number(wrapper.getAttribute(targetIdAttr) || 0);
        if (!targetId) {
            return;
        }

        var textarea = form.querySelector('[data-comment-input]');
        if (!textarea) {
            return;
        }
        var commentText = textarea.value.trim();
        if (!commentText) {
            return;
        }

        var payload = { comment: commentText };
        var method;
        if (interactionType === 'discussion') {
            method = 'add_discussion_comment';
            payload.discussion_id = targetId;
        } else if (interactionType === 'item') {
            method = 'add_item_comment';
            payload.item_id = targetId;
            var identifier = wrapper.querySelector('.feed-reactions').getAttribute('data-item-identifier');
            if (identifier) {
                payload.item_identifier = Number(identifier);
            }
        } else {
            return;
        }

        payload.user_id = currentUserId;

        textarea.disabled = true;

        sendRequest(method, payload).then(function (result) {
            textarea.disabled = false;
            if (result && result.success && result.comment) {
                var commentList = wrapper.querySelector('[data-comment-list]');
                var emptyNotice = wrapper.querySelector('[data-comment-empty]');
                appendComment(commentList, emptyNotice, result.comment, interactionType);
                textarea.value = '';
            } else if (result && result.error) {
                console.error('Unable to add comment:', result.error);
            }
        }).catch(function (error) {
            textarea.disabled = false;
            console.error('Comment request failed:', error);
        });
    }

    function handleCommentDelete(event) {
        var deleteButton = event.target.closest('[data-comment-delete]');
        if (!deleteButton) {
            return;
        }
        event.preventDefault();

        var commentElement = deleteButton.closest('.feed-comment');
        var wrapper = deleteButton.closest('[data-feed-interaction]');
        if (!commentElement || !wrapper) {
            return;
        }

        if (!confirm('Delete this comment?')) {
            return;
        }

        var commentId = Number(commentElement.getAttribute('data-comment-id') || 0);
        if (!commentId) {
            return;
        }

        var interactionType = wrapper.getAttribute('data-feed-interaction');
        var method;
        if (interactionType === 'discussion') {
            method = 'delete_discussion_comment';
        } else if (interactionType === 'item') {
            method = 'delete_item_comment';
        } else {
            return;
        }

        sendRequest(method, { comment_id: commentId }).then(function (result) {
            if (result && result.success) {
                var commentList = wrapper.querySelector('[data-comment-list]');
                var emptyNotice = wrapper.querySelector('[data-comment-empty]');
                commentElement.remove();
                if (commentList && commentList.children.length === 0 && emptyNotice) {
                    emptyNotice.classList.remove('hidden');
                    commentList.classList.add('hidden');
                }
            } else {
                console.error('Failed to delete comment', result);
            }
        }).catch(function (error) {
            console.error('Delete comment request failed:', error);
        });
    }

    document.addEventListener('click', handleReactionClick);
    document.addEventListener('submit', handleCommentSubmit);
    document.addEventListener('click', handleCommentDelete);
})();
