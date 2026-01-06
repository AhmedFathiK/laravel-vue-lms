<script setup>
import { Placeholder } from '@tiptap/extension-placeholder'
import { Table } from '@tiptap/extension-table'
import { TableCell } from '@tiptap/extension-table-cell'
import { TableHeader } from '@tiptap/extension-table-header'
import { TableRow } from '@tiptap/extension-table-row'
import { TextAlign } from '@tiptap/extension-text-align'
import { Underline } from '@tiptap/extension-underline'
import { StarterKit } from '@tiptap/starter-kit'
import {
  EditorContent,
  useEditor,
} from '@tiptap/vue-3'

const props = defineProps({
  modelValue: {
    type: String,
    required: true,
  },
  placeholder: {
    type: String,
    required: false,
  },
})

const emit = defineEmits(['update:modelValue'])

const editorRef = ref()

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit,
    TextAlign.configure({
      types: [
        'heading',
        'paragraph',
      ],
    }),
    Placeholder.configure({ placeholder: props.placeholder ?? 'Write something here...' }),
    Underline,
    Table.configure({
      resizable: true,
    }),
    TableRow,
    TableHeader,
    TableCell,
  ],
  onUpdate() {
    if (!editor.value)
      return
    emit('update:modelValue', editor.value.getHTML())
  },
})

watch(() => props.modelValue, () => {
  const isSame = editor.value?.getHTML() === props.modelValue
  if (isSame)
    return
  editor.value?.commands.setContent(props.modelValue)
})
</script>

<template>
  <div>
    <div
      v-if="editor"
      class="d-flex gap-2 py-2 px-6 flex-wrap align-center editor"
    >
      <IconBtn
        size="small"
        rounded
        :variant="editor.isActive('bold') ? 'tonal' : 'text'"
        :color="editor.isActive('bold') ? 'primary' : 'default'"
        @click="editor.chain().focus().toggleBold().run()"
      >
        <VIcon icon="tabler-bold" />
      </IconBtn>

      <IconBtn
        size="small"
        rounded
        :variant="editor.isActive('underline') ? 'tonal' : 'text'"
        :color="editor.isActive('underline') ? 'primary' : 'default'"
        @click="editor.commands.toggleUnderline()"
      >
        <VIcon icon="tabler-underline" />
      </IconBtn>

      <IconBtn
        size="small"
        rounded
        :variant="editor.isActive('italic') ? 'tonal' : 'text'"
        :color="editor.isActive('italic') ? 'primary' : 'default'"
        @click="editor.chain().focus().toggleItalic().run()"
      >
        <VIcon
          icon="tabler-italic"
          class="font-weight-medium"
        />
      </IconBtn>

      <IconBtn
        size="small"
        rounded
        :variant="editor.isActive('strike') ? 'tonal' : 'text'"
        :color="editor.isActive('strike') ? 'primary' : 'default'"
        @click="editor.chain().focus().toggleStrike().run()"
      >
        <VIcon icon="tabler-strikethrough" />
      </IconBtn>

      <IconBtn
        size="small"
        rounded
        :variant="editor.isActive({ textAlign: 'left' }) ? 'tonal' : 'text'"
        :color="editor.isActive({ textAlign: 'left' }) ? 'primary' : 'default'"
        @click="editor.chain().focus().setTextAlign('left').run()"
      >
        <VIcon icon="tabler-align-left" />
      </IconBtn>

      <IconBtn
        size="small"
        rounded
        :color="editor.isActive({ textAlign: 'center' }) ? 'primary' : 'default'"
        :variant="editor.isActive({ textAlign: 'center' }) ? 'tonal' : 'text'"
        @click="editor.chain().focus().setTextAlign('center').run()"
      >
        <VIcon icon="tabler-align-center" />
      </IconBtn>

      <IconBtn
        size="small"
        rounded
        :variant="editor.isActive({ textAlign: 'right' }) ? 'tonal' : 'text'"
        :color="editor.isActive({ textAlign: 'right' }) ? 'primary' : 'default'"
        @click="editor.chain().focus().setTextAlign('right').run()"
      >
        <VIcon icon="tabler-align-right" />
      </IconBtn>

      <IconBtn
        size="small"
        rounded
        :variant="editor.isActive({ textAlign: 'justify' }) ? 'tonal' : 'text'"
        :color="editor.isActive({ textAlign: 'justify' }) ? 'primary' : 'default'"
        @click="editor.chain().focus().setTextAlign('justify').run()"
      >
        <VIcon icon="tabler-align-justified" />
      </IconBtn>

      <VDivider vertical />

      <IconBtn
        size="small"
        rounded
        variant="text"
        color="default"
        title="Insert Table"
        @click="editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()"
      >
        <VIcon icon="tabler-table" />
      </IconBtn>

      <template v-if="editor.isActive('table')">
        <IconBtn
          size="small"
          rounded
          variant="text"
          color="default"
          title="Add Column Before"
          @click="editor.chain().focus().addColumnBefore().run()"
        >
          <VIcon icon="tabler-column-insert-left" />
        </IconBtn>

        <IconBtn
          size="small"
          rounded
          variant="text"
          color="default"
          title="Add Column After"
          @click="editor.chain().focus().addColumnAfter().run()"
        >
          <VIcon icon="tabler-column-insert-right" />
        </IconBtn>

        <IconBtn
          size="small"
          rounded
          variant="text"
          color="default"
          title="Delete Column"
          @click="editor.chain().focus().deleteColumn().run()"
        >
          <VIcon icon="tabler-column-remove" />
        </IconBtn>

        <IconBtn
          size="small"
          rounded
          variant="text"
          color="default"
          title="Add Row Before"
          @click="editor.chain().focus().addRowBefore().run()"
        >
          <VIcon icon="tabler-row-insert-top" />
        </IconBtn>

        <IconBtn
          size="small"
          rounded
          variant="text"
          color="default"
          title="Add Row After"
          @click="editor.chain().focus().addRowAfter().run()"
        >
          <VIcon icon="tabler-row-insert-bottom" />
        </IconBtn>

        <IconBtn
          size="small"
          rounded
          variant="text"
          color="default"
          title="Delete Row"
          @click="editor.chain().focus().deleteRow().run()"
        >
          <VIcon icon="tabler-row-remove" />
        </IconBtn>

        <IconBtn
          size="small"
          rounded
          variant="text"
          color="error"
          title="Delete Table"
          @click="editor.chain().focus().deleteTable().run()"
        >
          <VIcon icon="tabler-table-off" />
        </IconBtn>
      </template>
    </div>

    <VDivider />

    <EditorContent
      ref="editorRef"
      :editor="editor"
    />
  </div>
</template>

<style lang="scss">
.ProseMirror {
  padding: 0.5rem;
  min-block-size: 15vh;
  outline: none;

  p {
    margin-block-end: 0;
  }

  p.is-editor-empty:first-child::before {
    block-size: 0;
    color: #adb5bd;
    content: attr(data-placeholder);
    float: inline-start;
    pointer-events: none;
  }

  table {
    border-collapse: collapse;
    table-layout: fixed;
    width: 100%;
    margin: 0;
    overflow: hidden;

    td,
    th {
      min-width: 1em;
      border: 1px solid #ced4da;
      padding: 3px 5px;
      vertical-align: top;
      box-sizing: border-box;
      position: relative;

      > * {
        margin-bottom: 0;
      }
    }

    th {
      font-weight: bold;
      text-align: left;
      background-color: rgba(var(--v-theme-on-surface), 0.05);
    }

    .selectedCell:after {
      z-index: 2;
      position: absolute;
      content: "";
      left: 0;
      right: 0;
      top: 0;
      bottom: 0;
      background: rgba(200, 200, 255, 0.4);
      pointer-events: none;
    }

    .column-resize-handle {
      position: absolute;
      right: -2px;
      top: 0;
      bottom: -2px;
      width: 4px;
      background-color: #adf;
      pointer-events: none;
    }
  }

  .tableWrapper {
    margin: 1rem 0;
    overflow-x: auto;
  }

  &.resize-cursor {
    cursor: ew-resize;
    cursor: col-resize;
  }
}
</style>
