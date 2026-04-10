import { useState, useEffect, FormEventHandler } from 'react';
import axios from 'axios';
import PrimaryButton from './PrimaryButton';
import SecondaryButton from './SecondaryButton';
import TextInput from './TextInput';

interface User {
    id: number;
    name: string;
    email: string;
}

interface Comment {
    id: number;
    document_id: number;
    user_id: number;
    content: string;
    parent_id: number | null;
    created_at: string;
    user: User;
    replies?: Comment[];
}

interface CommentsProps {
    documentId: number;
    currentUserId: number;
}

export default function Comments({ documentId, currentUserId }: CommentsProps) {
    const [comments, setComments] = useState<Comment[]>([]);
    const [newComment, setNewComment] = useState('');
    const [replyTo, setReplyTo] = useState<number | null>(null);
    const [replyContent, setReplyContent] = useState('');
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editContent, setEditContent] = useState('');
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        fetchComments();
    }, [documentId]);

    const fetchComments = async () => {
        try {
            const response = await axios.get('/comments', {
                params: { document_id: documentId },
            });
            setComments(response.data);
        } catch (error) {
            console.error('Failed to fetch comments:', error);
        }
    };

    const handleAddComment: FormEventHandler = async (e) => {
        e.preventDefault();
        if (!newComment.trim()) return;

        setLoading(true);
        try {
            await axios.post('/comments', {
                document_id: documentId,
                content: newComment,
            });
            setNewComment('');
            fetchComments();
        } catch (error) {
            console.error('Failed to add comment:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleAddReply = async (parentId: number) => {
        if (!replyContent.trim()) return;

        setLoading(true);
        try {
            await axios.post('/comments', {
                document_id: documentId,
                content: replyContent,
                parent_id: parentId,
            });
            setReplyContent('');
            setReplyTo(null);
            fetchComments();
        } catch (error) {
            console.error('Failed to add reply:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleUpdateComment = async (commentId: number) => {
        if (!editContent.trim()) return;

        setLoading(true);
        try {
            await axios.put(`/comments/${commentId}`, {
                content: editContent,
            });
            setEditingId(null);
            setEditContent('');
            fetchComments();
        } catch (error) {
            console.error('Failed to update comment:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleDeleteComment = async (commentId: number) => {
        if (!confirm('Are you sure you want to delete this comment?')) return;

        try {
            await axios.delete(`/comments/${commentId}`);
            fetchComments();
        } catch (error) {
            console.error('Failed to delete comment:', error);
        }
    };

    const formatDate = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now.getTime() - date.getTime();
        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);

        if (days > 7) {
            return date.toLocaleDateString();
        } else if (days > 0) {
            return `${days}d ago`;
        } else if (hours > 0) {
            return `${hours}h ago`;
        } else if (minutes > 0) {
            return `${minutes}m ago`;
        } else {
            return 'Just now';
        }
    };

    const renderComment = (comment: Comment, isReply = false) => (
        <div key={comment.id} className={`${isReply ? 'ml-8 mt-3' : 'mt-4'}`}>
            <div className="rounded-lg bg-gray-50 p-4">
                <div className="flex items-start justify-between">
                    <div className="flex-1">
                        <div className="flex items-center gap-2">
                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">
                                {comment.user.name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <p className="text-sm font-semibold text-gray-900">
                                    {comment.user.name}
                                </p>
                                <p className="text-xs text-gray-500">
                                    {formatDate(comment.created_at)}
                                </p>
                            </div>
                        </div>
                        {editingId === comment.id ? (
                            <div className="mt-3">
                                <textarea
                                    value={editContent}
                                    onChange={(e) => setEditContent(e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    rows={3}
                                />
                                <div className="mt-2 flex gap-2">
                                    <SecondaryButton
                                        onClick={() => setEditingId(null)}
                                        disabled={loading}
                                    >
                                        Cancel
                                    </SecondaryButton>
                                    <PrimaryButton
                                        onClick={() => handleUpdateComment(comment.id)}
                                        disabled={loading}
                                    >
                                        Save
                                    </PrimaryButton>
                                </div>
                            </div>
                        ) : (
                            <p className="mt-2 text-sm text-gray-700">{comment.content}</p>
                        )}
                    </div>
                    {comment.user_id === currentUserId && editingId !== comment.id && (
                        <div className="flex gap-2">
                            <button
                                onClick={() => {
                                    setEditingId(comment.id);
                                    setEditContent(comment.content);
                                }}
                                className="text-xs text-indigo-600 hover:text-indigo-800"
                            >
                                Edit
                            </button>
                            <button
                                onClick={() => handleDeleteComment(comment.id)}
                                className="text-xs text-red-600 hover:text-red-800"
                            >
                                Delete
                            </button>
                        </div>
                    )}
                </div>

                {!isReply && editingId !== comment.id && (
                    <button
                        onClick={() => setReplyTo(comment.id)}
                        className="mt-2 text-xs font-semibold text-indigo-600 hover:text-indigo-800"
                    >
                        Reply
                    </button>
                )}

                {replyTo === comment.id && (
                    <div className="mt-3">
                        <textarea
                            value={replyContent}
                            onChange={(e) => setReplyContent(e.target.value)}
                            placeholder="Write a reply..."
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            rows={2}
                        />
                        <div className="mt-2 flex gap-2">
                            <SecondaryButton
                                onClick={() => {
                                    setReplyTo(null);
                                    setReplyContent('');
                                }}
                                disabled={loading}
                            >
                                Cancel
                            </SecondaryButton>
                            <PrimaryButton
                                onClick={() => handleAddReply(comment.id)}
                                disabled={loading}
                            >
                                Reply
                            </PrimaryButton>
                        </div>
                    </div>
                )}
            </div>

            {comment.replies && comment.replies.length > 0 && (
                <div className="mt-2">
                    {comment.replies.map((reply) => renderComment(reply, true))}
                </div>
            )}
        </div>
    );

    return (
        <div className="space-y-4">
            <h3 className="text-lg font-semibold text-gray-900">
                Comments ({comments.length})
            </h3>

            {/* Add Comment Form */}
            <form onSubmit={handleAddComment} className="space-y-3">
                <textarea
                    value={newComment}
                    onChange={(e) => setNewComment(e.target.value)}
                    placeholder="Write a comment..."
                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    rows={3}
                />
                <PrimaryButton type="submit" disabled={loading || !newComment.trim()}>
                    Add Comment
                </PrimaryButton>
            </form>

            {/* Comments List */}
            <div className="space-y-2">
                {comments.length === 0 ? (
                    <p className="py-8 text-center text-sm text-gray-500">
                        No comments yet. Be the first to comment!
                    </p>
                ) : (
                    comments.map((comment) => renderComment(comment))
                )}
            </div>
        </div>
    );
}
